---
title: "External net 创建问题处理"
date: 2017-01-13T17:25:00
author: Agent-Max & Maxwell Li
categories: [3]
tags: [5, 10, 11, 19, 20, 25, 27]
url: http://47.84.100.47/?p=82
---


> 问题发现 北京时间 1月 12日上午，王戊林在检查 CI 上 Functest …

---

## **问题发现**

北京时间 1月 12日上午，王戊林在检查 CI 上 Functest 测试结果时发现，compass4nfv 部署的 OpenStack 没有创建外部网络，导致 Functest 测试失败。

## **问题定位**

### **Step1 查看 ansible log**

<pre class="wp-block-syntaxhighlighter-code">2017-01-11 02:16:03,996 p=24880 u=root |  TASK [ext-network : create external net] ***************************************
2017-01-11 02:16:04,023 p=24880 u=root |  skipping: [host1]
2017-01-11 02:16:04,028 p=24880 u=root |  skipping: [host2]
2017-01-11 02:16:04,037 p=24880 u=root |  skipping: [host3]
2017-01-11 02:16:04,040 p=24880 u=root |  TASK [ext-network : create external subnet] ************************************
2017-01-11 02:16:04,062 p=24880 u=root |  skipping: [host1]
2017-01-11 02:16:04,069 p=24880 u=root |  skipping: [host2]
2017-01-11 02:16:04,075 p=24880 u=root |  skipping: [host3]</pre>

原本应该在 host1 上执行的 create external net 和 create external subnet 两个 task 都直接被 skip 了。初步判断是 task 中的判断出了问题。立刻将 virtua-pod4 离线，进入环境定位问题。

### **Step2 查看对应 Playbook**

<pre class="wp-block-syntaxhighlighter-code">- name: create external net 
  shell:
    . /opt/admin-openrc.sh;
    neutron net-create \
        {{ public_net_info.network }} \
        --provider:network_type {{ public_net_info.type }} \
        --provider:physical_network {{ public_net_info.provider_network }} \
        --router:external "True"
  when: public_net_info.enable == True
        and inventory_hostname == groups['controller'][0]

- name: create external subnet
  shell:
    . /opt/admin-openrc.sh;
    neutron subnet-create \
        --name {{ public_net_info.subnet }} \
        --gateway {{ public_net_info.external_gw }} \
        --disable-dhcp \
        --allocation-pool \
        start={{ public_net_info.floating_ip_start }},end={{ public_net_info.floating_ip_end }} \
        {{ public_net_info.network }} {{ public_net_info.floating_ip_cidr }}
  when: public_net_info.enable == True
        and inventory_hostname == groups['controller'][0]</pre>

猜测 when 的写法不合规范。然而令人困惑的时，本地测试时一切都正常，能够创建外部网络，并且部署成功后还能够拉起实例。而且黄翔宇也告诉我，他在 102 环境上和 104 环境上用同样的代码，也出现了这个问题。102 能够成功创建外部网络，而 104 却不可以。同样的代码，不同的环境会造成不同的结果，肯定是不能接受的。

### **Step3 对判断进行分析**

分析 `public_net_info.enable` 的值，在 group_vars/all 中找到 public_net_info 变量：

<pre class="wp-block-syntaxhighlighter-code">'public_net_info': {'no_gateway': 'False', 'external_gw': '192.168.103.1', 'floating_ip_cidr': '192.168.103.0/24', 'network': 'ext-net', 'enable_dhcp': 'False', 'segment_id': 1000, 'subnet': 'ext-subnet', 'type': 'flat', 'enable': 'True', 'floating_ip_start': '192.168.103.223', 'floating_ip_end': '192.168.103.240', 'router': 'router-ext', 'provider_network': 'physnet'}</pre>

发现 `public_net_info.enable` 值为 ‘True’，是一个字符串。回到本地环境，在 group_vars/all 中找到 public_net_info 变量：

<pre class="wp-block-syntaxhighlighter-code">'public_net_info': {'no_gateway': False, 'external_gw': '192.168.116.1', 'floating_ip_cidr': '192.168.116.0/24', 'network': 'ext-net', 'enable_dhcp': False, 'segment_id': 1000, 'subnet': 'ext-subnet', 'type': 'flat', 'enable': True, 'floating_ip_start': '192.168.116.223', 'floating_ip_end': '192.168.116.240', 'router': 'router-ext', 'provider_network': 'physnet'}</pre>

本地环境中，`public_net_info.enable` 值为‘True’。group_vars/all 文件是生成的，为什么会造成如此差异。

### **Step4 追根溯源**

public_net_info 是在 network.yml 文件中导入的。突然想起，本地部署为了方便，很早以前就写了一份 network.yml 放在代码外面，一直没有进行修改。而 CI 所采用的 network.yml 文件是在代码内部，当初进行 yaml 检视时，由于无法通过 yamllint，给所有的 True 和 False 都加上了双引号。

<pre class="wp-block-syntaxhighlighter-code">diff --git a/deploy/conf/vm_environment/huawei-virtual4/network.yml b/deploy/conf/vm_environment/huawe
index d787689..5a7f3d0 100644
--- a/deploy/conf/vm_environment/huawei-virtual4/network.yml
+++ b/deploy/conf/vm_environment/huawei-virtual4/network.yml
@@ -75,15 +76,15 @@ public_vip:
 
 onos_nic: eth2
 public_net_info:
-  enable: True
+  enable: "True"
   network: ext-net
   type: flat
   segment_id: 1000
   subnet: ext-subnet
   provider_network: physnet
   router: router-ext
-  enable_dhcp: False
-  no_gateway: False
+  enable_dhcp: "False"
+  no_gateway: "False"
   external_gw: "192.168.103.1"
   floating_ip_cidr: "192.168.103.0/24"
   floating_ip_start: "192.168.103.101"</pre>

### **Step5 提交 Patch，修复 Bug**

<pre class="wp-block-syntaxhighlighter-code">diff --git a/deploy/adapters/ansible/roles/ext-network/tasks/main.yml b/deploy/adapters/ansible/roles/
index 0fc3ee3..d212dd9 100644
--- a/deploy/adapters/ansible/roles/ext-network/tasks/main.yml
+++ b/deploy/adapters/ansible/roles/ext-network/tasks/main.yml
@@ -29,7 +29,7 @@
         --provider:network_type {{ public_net_info.type }} \
         --provider:physical_network {{ public_net_info.provider_network }} \
         --router:external "True"
-  when: public_net_info.enable == True
+  when: public_net_info.enable == "True"
         and inventory_hostname == groups['controller'][0]
 
 - name: create external subnet
@@ -42,5 +42,5 @@
         --allocation-pool \
         start={{ public_net_info.floating_ip_start }},end={{ public_net_info.floating_ip_end }} \
         {{ public_net_info.network }} {{ public_net_info.floating_ip_cidr }}
-  when: public_net_info.enable == True
+  when: public_net_info.enable == "True"
         and inventory_hostname == groups['controller'][0]</pre>

修改后在本地进行测试和验证，然后提交 Patch。

Patch 地址：[FIX external net create](https://gerrit.opnfv.org/gerrit/#/c/26903/)

## **Bug 引入原因**

<ul class="wp-block-list">
- CI verify 取消了 Functest 测试，无法对部署的 OpenStack 进行验证。

- Yamllint test Patch 修改了 network.yml 文件，而本地一直在使用修改之前的 network.yml 文件进行验证。

## **问题反思**

<ol class="wp-block-list">
- 本地测试不能够证明什么，CI 测试尤为重要。

- 此 Bug 和之前的 Ceph user Bug 均由 Yamllint test Patch 引入。为了通过 Yamllint test，修改了大量的 yaml 文件，虽然可以算作是纯体力劳动，但越是这样的工作越需要细致，稍有不慎就会出错。
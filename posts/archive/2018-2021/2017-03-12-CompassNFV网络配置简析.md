---
title: "CompassNFV 网络配置简析"
date: 2017-03-12T10:09:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [7, 8, 10, 19, 24, 30]
url: http://47.84.100.47/?p=120
---


> 默认网络结构 OpenStack 默认需要三个网络：mgmt、storage、e…

---

## **默认网络结构**

OpenStack 默认需要三个网络：mgmt、storage、external（此处的 external 网络指的是 OpenStack 的 provider 网络，建在 ovs 网桥 br-prv 上）。这三个网络可以共享使用同一个物理网口，也可以各自使用不同的网口。具体网络结构如下图：

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1632" height="1356" src="https://maxwellii.com/wp-content/uploads/2023/11/e789a9e79086e983a8e7bdb2e7bb84e7bd91e59bbe.jpg?w=1024" alt="" class="wp-image-121" srcset="http://47.84.100.47/wp-content/uploads/2023/11/e789a9e79086e983a8e7bdb2e7bb84e7bd91e59bbe.jpg 1632w, http://47.84.100.47/wp-content/uploads/2023/11/e789a9e79086e983a8e7bdb2e7bb84e7bd91e59bbe-300x249.jpg 300w, http://47.84.100.47/wp-content/uploads/2023/11/e789a9e79086e983a8e7bdb2e7bb84e7bd91e59bbe-1024x851.jpg 1024w, http://47.84.100.47/wp-content/uploads/2023/11/e789a9e79086e983a8e7bdb2e7bb84e7bd91e59bbe-768x638.jpg 768w, http://47.84.100.47/wp-content/uploads/2023/11/e789a9e79086e983a8e7bdb2e7bb84e7bd91e59bbe-1536x1276.jpg 1536w" sizes="(max-width: 1632px) 100vw, 1632px" /></figure>

针对此网络模型，在 Compass4NFV 代码 deploy/conf/ 目录下有一些范例文件可供参考。以 该目录下 network_cfg.yaml 为例，对每一个字段进行简单的解释。

<ul class="wp-block-list">
- provider_net_mappings 主要配置 br-prv 网桥。Compass4NFV 部署 OpenStack 结束后会创建一个 ext-net 网络作为 provider net，这个 br-prv 就是 ext-net 的物理网络。注意，interface 不能设置成 eth0，因为安装网络的名称就是 eth0。

<pre class="wp-block-syntaxhighlighter-code">provider_net_mappings:
  - name: br-prv
    network: physnet
    interface: eth1
    type: ovs
    role:
      - controller
      - compute</pre>

<ul class="wp-block-list">
- sys_intf_mappings 配置 OpenStack 的三个网络，需要将 vlan_tag 修改成交换机配置中相应的 vlan 标记。如果 mgmt、storage、external 不共享网络，即各自分离，则需要修改相应的 interface 值（external 网络应该修改 br-prv 的 interface）。

<pre class="wp-block-syntaxhighlighter-code">sys_intf_mappings:
  - name: mgmt
    interface: eth1
    vlan_tag: 101
    type: vlan
    role:
      - controller
      - compute

  - name: storage
    interface: eth1
    vlan_tag: 102
    type: vlan
    role:
      - controller
      - compute

  - name: external
    interface: br-prv
    type: ovs
    role:
      - controller
      - compute</pre>

<ul class="wp-block-list">
- ip_settings 配置各个网络的 ip range，ip range 中的 ip 会被分配给各个节点。由于 mgmt 网络和 storage 网络不需要通外网，所以可以使用默认的虚拟 ip。而 external 网络需要访问外网，所以需要配置实际可用的 cidr 和 gateway。

<pre class="wp-block-syntaxhighlighter-code">ip_settings:
  - name: mgmt
    ip_ranges:
      - - "172.16.1.1"
        - "172.16.1.254"
    cidr: "172.16.1.0/24"
    role:
      - controller
      - compute

  - name: storage
    ip_ranges:
      - - "172.16.2.1"
        - "172.16.2.254"
    cidr: "172.16.2.0/24"
    role:
      - controller
      - compute

  - name: external
    ip_ranges:
      - - "192.168.50.210"
        - "192.168.50.220"
    cidr: "192.168.50.0/24"
    gw: "192.168.50.1"
    role:
      - controller
      - compute</pre>

<ul class="wp-block-list">
- internal_vip 指的是 endpoint 中的 internal url，ip 应和 mgmt 在同一子网内。

<pre class="wp-block-syntaxhighlighter-code">internal_vip:
  ip: 172.16.1.222
  netmask: "24"
  interface: mgmt</pre>

<ul class="wp-block-list">
- public_vip 提供给 horizon 使用，并且这个 ip 不能够在上面的 external ip range 内，否则有可能造成 ip 冲突。

<pre class="wp-block-syntaxhighlighter-code">public_vip:
  ip: 192.168.50.240
  netmask: "24"
  interface: external</pre>

<ul class="wp-block-list">
- public_net_info 配置 OpenStack provider 网络的信息，包括浮动 ip 范围等。

<pre class="wp-block-syntaxhighlighter-code">public_net_info:
  enable: "True"
  network: ext-net
  type: flat
  segment_id: 1000
  subnet: ext-subnet
  provider_network: physnet
  router: router-ext
  enable_dhcp: "False"
  no_gateway: "False"
  external_gw: "192.168.50.1"
  floating_ip_cidr: "192.168.50.0/24"
  floating_ip_start: "192.168.50.221"
  floating_ip_end: "192.168.50.231"</pre>

## **复杂网络结构**

西安实验室的服务器上有两个电口，作为 PXE 口。四个光口用作 OpenStack 的管理网络和业务网络，并且两两组成了 bond。另外，两个 bond 划分了不同的 vlan tag，这是交换机层面的网络配置了，在此不具体展开了。网络结构图如下：

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1778" height="955" src="https://maxwellii.com/wp-content/uploads/2023/11/e8a5bfe5ae89e7bb84e7bd91e59bbe-1.jpeg?w=1024" alt="" class="wp-image-129" srcset="http://47.84.100.47/wp-content/uploads/2023/11/e8a5bfe5ae89e7bb84e7bd91e59bbe-1.jpeg 1778w, http://47.84.100.47/wp-content/uploads/2023/11/e8a5bfe5ae89e7bb84e7bd91e59bbe-1-300x161.jpeg 300w, http://47.84.100.47/wp-content/uploads/2023/11/e8a5bfe5ae89e7bb84e7bd91e59bbe-1-1024x550.jpeg 1024w, http://47.84.100.47/wp-content/uploads/2023/11/e8a5bfe5ae89e7bb84e7bd91e59bbe-1-768x413.jpeg 768w, http://47.84.100.47/wp-content/uploads/2023/11/e8a5bfe5ae89e7bb84e7bd91e59bbe-1-1536x825.jpeg 1536w" sizes="(max-width: 1778px) 100vw, 1778px" /></figure>

为了实现 bond，在重命名网卡后加入了组 bond 脚本，主要是在 compass 虚拟机内对 cobbler 进行一些操作，相关操作可参考[cobbler bonding](http://cobbler.github.io/manuals/2.6.0/4/1/1_-_Bonding.html)，Patch 地址：[Support bond created](https://gerrit.opnfv.org/gerrit/#/c/30391/)。

为了创建 br-physnet2，需要修改 deploy/conf 目录下的 neutron_cfg.yaml 文件，加入 br-physnet2。

<pre class="wp-block-syntaxhighlighter-code">--- neutron_cfg.yaml	2017-03-21 16:30:21.915984423 +0800
+++ neutron_cfg_bak.yaml	2017-03-21 16:30:00.775654885 +0800
@@ -12,7 +12,5 @@
   tenant_network_type: vxlan
   network_vlan_ranges:
     - 'physnet:1:4094'
-    - 'physnet2:2:4000'
   bridge_mappings:
     - 'physnet:br-prv'
-    - 'physnet2:br-physnet2'</pre>

然后根据组网图对 network_cfg.yaml 文件进行修改。

<ul class="wp-block-list">
- bond_mappings 配置 bond 信息，对应网卡重命名需要在 DHA 文件中写入网卡名称和 mac 地址。host 下配置需要组 bond 的节点。

<pre class="wp-block-syntaxhighlighter-code">bond_mappings:
  - name: bond1
    host:
      - host1
      - host2
      - host3
    bond-slaves:
      - eth1
      - eth2
    bond-mode: 802.3ad
    bond-miimon: 100
    bond-lacp_rate: fast
    bond-xmit_hash_policy: layer2
    mtu: 9000
 
  - name: bond2
    host:
      - host1
      - host2
      - host3
    bond-slaves:
      - eth3
      - eth4
    bond-mode: 802.3ad
    bond-miimon: 100
    bond-lacp_rate: fast
    bond-xmit_hash_policy: layer2
    mtu: 9000</pre>

<ul class="wp-block-list">
- 在 provider_net_mappings 内加入 br-physnet2 相关信息。

<pre class="wp-block-syntaxhighlighter-code">provider_net_mappings:
  - name: br-prv
    network: physnet
    interface: bond1
    type: ovs
    role:
      - controller
      - compute

  - name: br-physnet2
    network: physnet
    interface: bond2
    type: ovs
    role:
      - controller
      - compute</pre>

<ul class="wp-block-list">
- 修改 mgmt、storage、external 网络的 interface 和 vlan_tag。西安实验室的 JumpHost 无法创建 br-external 网桥，provider 网络无法访问外网，因此给 external 网络打了 vlan tag，使其能够连接外网。

<pre class="wp-block-syntaxhighlighter-code">--- network_cfg.yaml	 2017-03-21 16:40:50.000000000 +0800
+++ network_cfg_bak.yaml	2017-02-08 11:49:46.000000000 +0800
@@ -21,25 +12,24 @@
 
 sys_intf_mappings:
   - name: mgmt
-    interface: bond1
-    vlan_tag: 551
+    interface: eth1
+    vlan_tag: 101
     type: vlan
     role:
       - controller
       - compute
 
   - name: storage
-    interface: bond1
-    vlan_tag: 550
+    interface: eth1
+    vlan_tag: 102
     type: vlan
     role:
       - controller
       - compute
 
   - name: external
-    interface: bond1
-    vlan_tag: 552
-    type: vlan
+    interface: br-prv
+    type: ovs
     role:
       - controller
       - compute</pre>
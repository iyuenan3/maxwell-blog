---
title: "Compassnfv Expansion"
date: 2016-12-20T15:39:00
author: Agent-Max & Maxwell Li
categories: [3]
tags: [5, 10, 21, 25, 27]
url: http://47.84.100.47/?p=38
---


> 本文从代码层面简单描述了扩容的实现过程。 在廊坊的时候就接到任务要对compas…

---

本文从代码层面简单描述了扩容的实现过程。

在廊坊的时候就接到任务要对compass4nfv实现扩容功能，前前后后花了近两周时间才完成。虽然任务比较艰巨，但是开发效率实在是有待提高。从初期的如何写入host信息到中期的如何引导虚拟机安装系统再到后期的引导ansible，自己一步一步摸索着走下去，学到了不少新知识，也对compass4nfv如何部署OpenStack也有了更加深入的了解。

注：本文所描述的OpenStack部署均为3+2模式，3个计算节点，2个控制节点，首次部署的节点为host1-5，新增计算节点为host6。

## **一、修改base.conf文件**

加入了expansion变量，当expansion=false时，部署OpenStack；当expansion=true时，进行扩容，增加一个或多个计算节点。

<pre class="wp-block-syntaxhighlighter-code">export EXPANSION=${EXPANSION:-"false"}</pre>

## **二、修改launch.sh脚本**

在launch.sh的main process中，进行扩容时，由于已经部署过OpenStack，只需要直接读取machines信息即可。加入以下代码

<pre class="wp-block-syntaxhighlighter-code">if [[ "$EXPANSION" == "false" ]]
then
...
else
    machines=`get_host_macs`
    if [[ -z $machines ]];then
        log_error "get_host_macs failed"
        exit 1
    fi

    log_info "deploy host macs: $machines"
fi</pre>

## **三、修改host_baremetal.sh/host_virtual.sh脚本**

`host_baremetal.sh` 脚本主要是为了从DHA文件中读取到host的mac地址，而 `host_virtual.sh` 还要兼顾到拉起虚拟机、创建mac地址等工作（原代码中默认虚拟部署的DHA文件中不写入mac地址，虚拟机的mac地址由 `host_virtual.sh` 脚本中的get_host_macs()函数自动生成）。

考虑到在部署OpenStack时已经从DHA文件读取或者自动生成5个mac地址，应该直接从 `work/deploy/switch_machines` 文件中读取，而不是再次生成或者从DHA文件中再读取一遍。不能够重新生成比较好理解，如果重新生成几个mac地址写入到 `switch_machines` 中，将会导致和现有的host1-5mac地址不同。但是也不能再次从DHA文件中读取，因为compass4nfv会部署DHA文件中的所有host，为了不影响host1-5的正常工作，必须重新写一份DHA文件，而且此DHA文件中只能存在需要创建的新host。

示例文件： `./deploy/conf/hardware_environment/expansion-sample/hardware_cluster_expansion.yml` 和 `./deploy/conf/vm_environment/virtual_cluster_expansion.yml`。

`host_baremetal.sh` 改动较小，贴上扩容前后的代码：

扩容前：

<pre class="wp-block-syntaxhighlighter-code">function get_host_macs() {
    machines=`echo $HOST_MACS | sed -e 's/,/'\',\''/g' -e 's/^/'\''/g' -e 's/$/'\''/g'`
    echo $machines
}</pre>

扩容后：

<pre class="wp-block-syntaxhighlighter-code">function get_host_macs() {
    if [[ "$EXPANSION" == "false" ]]; then
        machines=`echo $HOST_MACS | sed -e 's/,/'\',\''/g' -e 's/^/'\''/g' -e 's/$/'\''/g'`
        echo $machines > $WORK_DIR/switch_machines
    else
        machines_old=`cat $WORK_DIR/switch_machines`
        machines_add=`echo $HOST_MACS | sed -e 's/,/'\',\''/g' -e 's/^/'\''/g' -e 's/$/'\''/g'`
        echo $machines_add $machines_old > $WORK_DIR/switch_machines
        machines=`echo $machines_add $machines_old|sed 's/ /,/g'`
    fi
    echo $machines
}</pre>

由以上代码可以看出，在部署时（即 `$EXPANSION" == "false`），先将DHA文件中读取到的mac地址写入 `switch_machines` 文件中。当需要扩容时，先从 `switch_machines` 文件中获取到host1-5的mac地址，然后再从新的DHA文件中获取到host6的mac地址，再一起写入到 `switch_machines`。

`host_virtual.sh` 的改动稍微大一些，无非就是要多考虑DHA文件中没有mac地址，要自动生成的情况。代码如下：

扩容前：

<pre class="wp-block-syntaxhighlighter-code">function get_host_macs() {
    local mac_generator=${COMPASS_DIR}/deploy/mac_generator.sh
    local machines=
    if [[ $REDEPLOY_HOST == "true" ]]; then
        mac_array=`cat $WORK_DIR/switch_machines`
    else
        chmod +x $mac_generator
        mac_array=`$mac_generator $VIRT_NUMBER`
        echo $mac_array > $WORK_DIR/switch_machines
    fi
    machines=`echo $mac_array|sed 's/ /,/g'`
    echo $machines
}</pre>

扩容后：

<pre class="wp-block-syntaxhighlighter-code">function get_host_macs() {
    local mac_generator=${COMPASS_DIR}/deploy/mac_generator.sh
    local machines=
    if [[ $REDEPLOY_HOST == "true" ]]; then
        mac_array=`cat $WORK_DIR/switch_machines`
        machines=`echo $mac_array|sed 's/ /,/g'`
    else
        if [[ -z $HOST_MACS ]]; then
            if [[ "$EXPANSION" == "false" ]]; then
                chmod +x $mac_generator
                mac_array=`$mac_generator $VIRT_NUMBER`
                echo $mac_array > $WORK_DIR/switch_machines
                machines=`echo $mac_array|sed 's/ /,/g'`
            else
                machines_old=`cat $WORK_DIR/switch_machines`
                chmod +x $mac_generator
                machines_add=`$mac_generator $VIRT_NUMBER`
                echo $machines_add $machines_old > $WORK_DIR/switch_machines
                machines=`echo $machines_add $machines_old|sed 's/ /,/g'`
            fi
        else
            if [[ "$EXPANSION" == "false" ]]; then
                machines=`echo $HOST_MACS | sed -e 's/,/'\',\''/g' -e 's/^/'\''/g' -e 's/$/'\''/g'`
            else
                machines_old=`cat $WORK_DIR/switch_machines`
                machines_add=`echo $HOST_MACS | sed -e 's/,/'\',\''/g' -e 's/^/'\''/g' -e 's/$/'\''/g'`
                echo $machines_add $machines_old > $WORK_DIR/switch_machines
                machines=`echo $machines_add $machines_old|sed 's/ /,/g'`
            fi
        fi
    fi
    echo $machines
}</pre>

先考虑DHA文件中是否存在mac地址，如果存在，则和 `host_baremetal.sh` 的处理方式相同；如果不存在，则创建mac地址，并写入到 `switch_machines` 中。

## **四、修改client.py脚本**

client.py脚本应该算是改动最大的脚本之一，但是和之前的脚本如出一辙，增加一个对expansion的判断。

首先来看deploy函数。扩容前仅有 `if CONF.expansion == "false"` 部分，扩容后增加了else，代码如下：

<pre class="wp-block-syntaxhighlighter-code">def deploy():
    if CONF.expansion == "false":
        client = CompassClient()
        machines = client.get_machines()

        LOG.info('machines are %s', machines)

        client.add_subnets()
        adapter_id, os_id, flavor_id = client.get_adapter()
        cluster_id = client.add_cluster(adapter_id, os_id, flavor_id)

        client.add_cluster_hosts(cluster_id, machines)
        client.set_host_networking()
        client.set_cluster_os_config(cluster_id)

        if flavor_id:
            client.set_cluster_package_config(cluster_id)

        client.set_all_hosts_roles(cluster_id)
        client.deploy_clusters(cluster_id)

        LOG.info("compass OS installtion is begin")
        threading.Thread(target=print_ansible_log).start()
        client.get_installing_progress(cluster_id)
        client.check_dashboard_links(cluster_id)

    else:
        client = CompassClient()
        machines = client.get_machines()

        LOG.info('machines are %s', machines)

        client.add_subnets()

        status, response = client.client.list_clusters()
        cluster_id = 1
        for cluster in response:
            if cluster['name'] == CONF.cluster_name:
                cluster_id = cluster['id']

        client.add_cluster_hosts(cluster_id, machines)
        client.set_host_networking()
        client.set_cluster_os_config(cluster_id)

        client.set_cluster_package_config(cluster_id)

        client.set_all_hosts_roles(cluster_id)
        client.deploy_clusters(cluster_id)

        threading.Thread(target=print_ansible_log).start()
        client.get_installing_progress(cluster_id)</pre>

部署和扩容的大部分步骤相似，在扩容中删去了对 `cluster_id` 的获取，直接置为1，仅考虑单租户的情况。

然后再来看看else部分中对函数内部的修改。

<pre class="wp-block-syntaxhighlighter-code">def add_cluster_hosts(self, cluster_id, machines):</pre>

由于通过 `get_machines` 获取到的 `machines=[1,2,3,4,5,6]`，而 `add_cluster_hosts` 函数中获取到的 `hostnames=[host6]` ，为了使machines与hostnames对应，需要将machines中前五位删去。故在 `add_cluster_hosts` 中加入：`machines = machines[-len(hostnames):]`。最开始没有对machines进行处理，通过 `zip(machines, hostnames)` 后得到 `[1,"host6"]`，而 `machine_id=1` 已经被host1占用，导致无法加入host6。

<pre class="wp-block-syntaxhighlighter-code">def add_subnets(self):</pre>

在部署时，需要通过add_subnet()函数来获取subnet，但是在部署时将沿用部署时采用的subnet，代码如下：

<pre class="wp-block-syntaxhighlighter-code">if CONF.expansion == "false":
    status, resp = self.client.add_subnet(subnet)
    LOG.info('add subnet %s status %s response %s',
                 subnet, status, resp)
    if not self.is_ok(status):
        raise RuntimeError('failed to add subnet %s' % subnet)
    subnet_mapping[resp['subnet']] = resp['id']
else:
    for subnet_in_db in subnets_in_db:
        if subnet == subnet_in_db['subnet']:
            subnet_mapping[subnet] = subnet_in_db['id']</pre>

## **五、修改ansible脚本**

至此，能够成功写入host6信息，并且安装host6的操作系统。但是在安装系统后，发现host6中ansible并没有跑起来。进入到compass虚拟机中，将 `/var/ansible/run/openstack_mitaka-opnfv2/group_vars/all` 和 `/var/ansible/run/openstack_mitaka-opnfv2-expansion/group_vars /all` 进行对比，结果如下：

<pre class="wp-block-syntaxhighlighter-code">...

@@ -38,8 +38,8 @@
 internal_ip: "{{ ip_settings[inventory_hostname]['mgmt']['ip'] }}"
 internal_nic: mgmt

-vrouter_id_internal: 62
-vrouter_id_public: 62
+vrouter_id_internal: 102
+vrouter_id_public: 102

 identity_host: "{{ internal_ip }}"
 controllers_host: "{{ internal_ip }}"
@@ -50,8 +50,14 @@
 dashboard_host: "{{ internal_ip }}"

 haproxy_hosts:
+  host1: 172.16.1.1
+  host2: 172.16.1.2
+  host3: 172.16.1.3

...</pre>

在 `openstack_mitaka-opnfv2-expansion` 中的all文件中 `haproxy_hosts` 下没有任何内容，手动从 `openstack_mitaka-opnfv2` 中的all文件中拷入后，才能够正确安装compute节点。由此考虑，在 `./deploy/adapters/ansible/openstack_mitaka/roles/nova-compute/templates/`  目录下加入 `nova.conf`，此文件从 `./deploy/adapters/ansible/openstack_mitaka/templates/` 目录下拷贝过来，并略作修改。修改后对比如下：

<pre class="wp-block-syntaxhighlighter-code">--- ./nova.conf
+++ ../roles/nova-compute/templates/nova.conf
@@ -1,10 +1,6 @@
-{% set memcached_servers = [] %}
-{% for host in haproxy_hosts.values() %}
-{% set _ = memcached_servers.append('%s:11211'% host) %}
-{% endfor %}
-{% set memcached_servers = memcached_servers|join(',') %}
-
 [DEFAULT]
+block_device_allocate_retries=5
+block_device_allocate_retries_interval=300
 dhcpbridge_flagfile=/etc/nova/nova.conf
 dhcpbridge=/usr/bin/nova-dhcpbridge
 logdir=/var/log/nova
@@ -53,8 +49,6 @@
 notification_driver = nova.openstack.common.notifier.rpc_notifier
 notification_driver = ceilometer.compute.nova_notifier

-memcached_servers = {{ memcached_servers }}
-
 [database]
 # The SQLAlchemy connection string used to connect to the database
 connection = mysql://nova:{{ NOVA_DBPASS }}@{{ db_host }}/nova
@@ -74,7 +68,6 @@
 admin_tenant_name = service
 admin_user = nova
 admin_password = {{ NOVA_PASS }}
-memcached_servers = {{ memcached_servers }}

 [glance]
 host = {{ internal_vip.ip }}</pre>

并且修改 `./deploy/adapters/ansible/openstack_mitaka/roles/nova-compute/tasks/main.yml` 文件，修改如下：

<pre class="wp-block-syntaxhighlighter-code">--- a/deploy/adapters/ansible/openstack_mitaka/roles/nova-compute/tasks/main.yml
+++ b/deploy/adapters/ansible/openstack_mitaka/roles/nova-compute/tasks/main.yml
@@ -31,7 +31,7 @@
   when: ansible_os_family == "Debian"

 - name: update nova-compute conf
-  template: src=templates/{{ item }} dest=/etc/nova/{{ item }}
+  template: src={{ item }} dest=/etc/nova/{{ item }}
   with_items:
     - nova.conf
   notify:</pre>

以上，扩容功能的开发基本结束。关于compass4nfv部署OpenStack的过程，本人也有很多地方没有了解透测，欢迎各位留言交流。

完整的代码对比可参照以下网址：

https://gerrit.opnfv.org/gerrit/#/c/20859/

https://gerrit.opnfv.org/gerrit/#/c/20869/

另外，关于扩容功能的使用，可参考以下说明文档：

http://artifacts.opnfv.org/compass4nfv/review/20995/installationprocedure/expansion.html
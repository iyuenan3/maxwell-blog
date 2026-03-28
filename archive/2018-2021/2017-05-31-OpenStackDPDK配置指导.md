---
title: "OpenStack DPDK 配置指导"
date: 2017-05-31T10:54:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [8, 12, 14, 19, 20, 21, 24]
url: http://47.84.100.47/?p=165
---


> OpenStack Mitaka 版本已全面支持 OVS 使用 DPDK 提升网…

---

OpenStack Mitaka 版本已全面支持 OVS 使用 DPDK 提升网络交换性能，本文将详细介绍基于 Compass4NFV 部署的 OpenStack Mitaka 版本集成 DPDK 的操作过程（除特殊说明，以下操作均在计算节点上进行）。以下是此次配置的各个版本信息。

OpenStack Version: Mitaka

OS Version: Ubuntu 16.04

Open vSwitch Version: 2.5.0

Installer: Compass4NFV Colorado

## **Step1 配置大页**

在所有需要配置 DPDK 的计算节点上开启 Intel VT-d 配置。

将以下参数添加到 /etc/default/grub 中的 GRUB_CMDLINE_LINUX 配置项中。

<pre class="wp-block-syntaxhighlighter-code">default_hugepagesz=2M hugepagesz=2M hugepages=4096 iommu=pt intel_iommu=on</pre>

<ul class="wp-block-list">
- **本环境只作为测试使用，实际生产环境可配置 1GB 大页。**

- **hugepages 为需要使用的大页数量**

- **default_hugepagesz hugepagesz 配置大页大小，默认值为 2M**

执行命令 update-grub ，然后重启计算节点。

然后执行以下命令挂载大页到系统中。

<pre class="wp-block-syntaxhighlighter-code">$ mkdir -p /mnt/huge
$ mount -t hugetlbfs hugetlbfs /mnt/huge</pre>

挂载后，可以通过以下命令检查是否配置成功：

<pre class="wp-block-syntaxhighlighter-code">$ grep Huge /proc/meminfo 
AnonHugePages:    116736 kB
HugePages_Total:    4096
HugePages_Free:     4096
HugePages_Rsvd:        0
HugePages_Surp:        0
Hugepagesize:       2048 kB</pre>

## **Step2 切换 OVS 到 DPDK 版本**

OVS 集成 DPDK 和没有集成 DPDK 使用不同的二进制版本，使用以下命令将 OVS 切换为集成 DPDK 版本：

<pre class="wp-block-syntaxhighlighter-code">$ update-alternatives --set ovs-vswitchd /usr/lib/openvswitch-switch-dpdk/ovs-vswitchd-dpdk

update-alternatives: using /usr/lib/openvswitch-switch-dpdk/ovs-vswitchd-dpdk to provide /usr/sbin/ovs-vswitchd (ovs-vswitchd) in manual mode</pre>

可以通过以下命令检查是否切换成功：

<pre class="wp-block-syntaxhighlighter-code">$ update-alternatives --query ovs-vswitchd

Name: ovs-vswitchd
Link: /usr/sbin/ovs-vswitchd
Status: manual
Best: /usr/lib/openvswitch-switch/ovs-vswitchd
Value: /usr/lib/openvswitch-switch-dpdk/ovs-vswitchd-dpdk

Alternative: /usr/lib/openvswitch-switch-dpdk/ovs-vswitchd-dpdk
Priority: 50

Alternative: /usr/lib/openvswitch-switch/ovs-vswitchd
Priority: 100</pre>

## **Step3 配置网卡绑定用户态驱动**

DPDK 支持三种用户态网卡驱动：uio_pci_generic, igb_uio, vfio-pci。这里我们使用 uio_pci_generic。

### **Step3.1 加载驱动模块**

<pre class="wp-block-syntaxhighlighter-code">$ modprobe uio_pci_generic</pre>

### **Step3.2 查询被绑网卡的 pci 号**

<pre class="wp-block-syntaxhighlighter-code">$ lspci | grep Eth | awk '{print "ID=\"0000:"$1"\", NAME=\"eth"count++"\""}'

ID="0000:00:04.0", NAME="eth0"
ID="0000:00:05.0", NAME="eth1"
ID="0000:00:06.0", NAME="eth2"</pre>

### **Step3.3 绑定网卡驱动**

设置要使用 DPDK 网卡的 PCI 地址信息。

<pre class="wp-block-syntaxhighlighter-code">$ echo "pci 0000:00:06.0 uio_pci_generic" >> /etc/dpdk/interfaces
$ service dpdk restart</pre>

## **Step4 配置 OVS-DPDK 运行参数**

<pre class="wp-block-syntaxhighlighter-code">$ echo "DPDK_OPTS='--dpdk -c 0x1 -n 4 -m 2048 -w 0000:00:06.0 --vhost-owner libvirt-qemu:kvm --vhost-perm 0666'" >> /etc/default/openvswitch-switch
$ service openvswitch-switch restart
$ python /opt/setup_networks/setup_networks.py</pre>

其中 -c 为指定 OVS 运行时绑定哪些核，-m 为指定 OVS 使用的内存大小（单位 MB），具体根据业务需求配置。

## **Step5 配置 Neutron ML2**

<pre class="wp-block-syntaxhighlighter-code">$ crudini --set /etc/neutron/plugins/ml2/ml2_conf.ini ovs datapath_type netdev
$ crudini --set /etc/neutron/plugins/ml2/ml2_conf.ini ovs vhostuser_socket_dir /var/run/openvswitch
$ crudini --set /etc/neutron/plugins/ml2/openvswitch_agent.ini ovs datapath_type netdev
$ crudini --set /etc/neutron/plugins/ml2/openvswitch_agent.ini ovs vhostuser_socket_dir /var/run/openvswitch
$ service neutron-openvswitch-agent restart</pre>

## **Step6 配置 OVS 使用 DPDK 网卡**

<pre class="wp-block-syntaxhighlighter-code">$ ovs-vsctl del-port br-physnet2 eth2
$ ovs-vsctl add-port br-physnet2 dpdk0 -- set Interface dpdk0 type=dpdk</pre>

## **Step7 配置实例可使用的 CPU 核（此步骤可选）**

<pre class="wp-block-syntaxhighlighter-code">$ crudini --set /etc/nova/nova.conf DEFAULT vcpu_pin_set 0-2
$ service nova-compute restart</pre>

若不配置，则实例默认使用所有 CPU 核。

## **Step8 配置控制节点**

在控制节点上打开 metadata 相关配置（以下命令在所有控制节点上执行）。执行以下命令之前可以先检查一下相关配置是否已经开启，如果已经开启，可以跳过此步骤。

<pre class="wp-block-syntaxhighlighter-code">$ crudini --set /etc/neutron/dhcp_agent.ini DEFAULT enable_isolated_metadata True
$ crudini --set /etc/neutron/dhcp_agent.ini DEFAULT force_metadata True
$ for i in $(cat /opt/service | grep neutron); do service $i restart; done</pre>

## **Step9 测试验证**

### **Step9.1 Source RC 文件**

<pre class="wp-block-syntaxhighlighter-code">$ source /opt/admin-openrc.sh</pre>

### **Step9.2 创建 aggregate group 并且加入配置了 DPDK 的计算节点**

<pre class="wp-block-syntaxhighlighter-code">$ openstack aggregate create --zone=dpdk dpdk
$ openstack aggregate add host dpdk host4
$ openstack aggregate add host dpdk host5</pre>

### **Step9.3 上传 image**

<pre class="wp-block-syntaxhighlighter-code">$ wget 10.1.0.12/image/cirros-0.3.3-x86_64-disk.img
$ glance image-create --name "cirros" --file cirros-0.3.3-x86_64-disk.img --disk-format qcow2 --container-format bare</pre>

### **Step9.4 创建配置 flavor**

<pre class="wp-block-syntaxhighlighter-code">$ openstack flavor create  m1.tiny_huge --ram 512 --disk 1 --vcpus 1
$ openstack  flavor set \
    --property hw:mem_page_size=large \
    --property hw:cpu_policy=dedicated \
    --property hw:cpu_thread_policy=require \
    --property hw:numa_mempolicy=preferred \
    --property hw:numa_nodes=1 \
    --property hw:numa_cpus.0=0 \
    --property hw:numa_mem.0=512 \
    m1.tiny_huge</pre>

### **Step9.5 创建 DPDK 网络**

<pre class="wp-block-syntaxhighlighter-code">$ neutron net-create dpdk-net \
    --provider:network_type flat \
    --provider:physical_network physnet2 \
    --router:external "False"

$ neutron subnet-create \
    --name dpdk-subnet \
    --gateway 22.22.22.1 \
    dpdk-net 22.22.22.0/24</pre>

### **Step9.6 创建 router**

<pre class="wp-block-syntaxhighlighter-code">$ neutron router-create dpdk-router
$ neutron router-interface-add dpdk-router dpdk-subnet
$ neutron router-gateway-set dpdk-router ext-net</pre>

### **Step9.7 利用 DPDK 网络起实例**

<pre class="wp-block-syntaxhighlighter-code">$ openstack server create \
    --flavor m1.tiny_huge \
    --image cirros \
    --nic net-id=$(neutron net-list | grep dpdk-net | awk '{print $2}') \
    --availability-zone dpdk dpdk-demo</pre>

### **Step9.8 给实例分配 floating ip**

<pre class="wp-block-syntaxhighlighter-code">floating_ip=$(neutron floatingip-create ext-net | grep floating_ip_address | awk '{print $4}')
nova floating-ip-associate dpdk-demo $floating_ip</pre>

在不同的计算节点上起实例，可以相互 ping 通，并且在计算节点上可以看到实例绑核成功：

<pre class="wp-block-syntaxhighlighter-code">$ virsh vcpuinfo 1

VCPU:           0
CPU:            0
State:          running
CPU time:       25.9s
CPU Affinity:   y---</pre>

如果不需要绑核，在 flavor 设置中，只需要配置大页即可。

<pre class="wp-block-syntaxhighlighter-code">$ openstack flavor set --property hw:mem_page_size=large m1.tiny_huge</pre>
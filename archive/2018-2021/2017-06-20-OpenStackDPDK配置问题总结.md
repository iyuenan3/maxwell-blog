---
title: "OpenStack DPDK 配置问题总结"
date: 2017-06-20T13:46:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [8, 11, 12, 19, 21, 24]
url: http://47.84.100.47/?p=178
---


> 收到 OpenStack Mitaka Ubuntu 16.04 环境上配置 D…

---

收到 OpenStack Mitaka Ubuntu 16.04 环境上配置 DPDK 的需求，最近几天一直在调试。因为手头上只有一台物理机，所以只能进行虚拟部署（控制节点和计算节点均为起在物理机上的虚拟机）。本文主要记录配置 DPDK 过程中碰到的三个问题：ovs-vswitchd 进程无法启动、两台计算节点上的实例无法 ping 通、实例无法连接 metadate。

## **1. ovs-vswitchd 进程无法启动**

在配置 OVS-DPDK 运行参数时，起初在 /etc/default/openvswitch-switch 内加入以下内容：

<pre class="wp-block-syntaxhighlighter-code">DPDK_OPTS='--dpdk -c 0x1 -n 4 -m 2048 --vhost-owner libvirt-qemu:kvm --vhost-perm 0666'</pre>

然后重启 openvswitch-switch 服务，发现 ovs-switchd 进程没有过启动：

<pre class="wp-block-syntaxhighlighter-code">$ ps aux | grep ovs-vswitchd

root     16887  0.0  0.0  12948  1004 pts/0    S+   02:16   0:00 grep --color=auto ovs-vswitchd</pre>

但是注释掉 /etc/default/openvswitch-switch 文件内的 DPDK_OPTS，openvswitch 服务就能正常启动，怀疑是 DPDK_OPTS 配置问题。谷歌后找到这个 [launchpad](https://bugs.launchpad.net/ubuntu/+source/openvswitch-dpdk/+bug/1547463)，提到了 DPDK_OPTS 参数无法被 ovs-vswitchd 识别，是 OVS 的一个 bug，但是应该已经被修复。尝试注释掉 DPDK_OPTS，重启 openvswitch 服务，然后 kill 掉所有 ovs-vswitchd 进程，再配合 DPDK 配置项手动启动 ovs-vswitchd 进程。

<pre class="wp-block-syntaxhighlighter-code">$ ps aux | grep ovs-vswitchd

root     17766  0.0  0.0  36812  2680 ?        S<s  02:22   0:00 ovs-vswitchd: monitoring pid 17767 (healthy)
root     17767  0.5  0.3 406652 54744 ?        S<Ll 02:22   0:00 ovs-vswitchd unix:/var/run/openvswitch/db.sock -vconsole:emer -vsyslog:err -vfile:info --mlockall --no-chdir --log-file=/var/log/openvswitch/ovs-vswitchd.log --pidfile=/var/run/openvswitch/ovs-vswitchd.pid --detach --monitor
root     18078  0.0  0.0  12948  1016 pts/0    S+   02:22   0:00 grep --color=auto ovs-vswitchd

$ kill -9 17766
$ kill -9 17767
$ ovs-vswitchd --dpdk -c 0x1 -n 2 -- unix:/var/run/openvswitch/db.sock --pidfile --detach

2017-05-31T09:25:14Z|00001|dpdk|INFO|No -vhost_sock_dir provided - defaulting to /var/run/openvswitch
EAL: Detected lcore 0 as core 0 on socket 0
EAL: Detected lcore 1 as core 0 on socket 0
EAL: Detected lcore 2 as core 0 on socket 0
EAL: Detected lcore 3 as core 0 on socket 0
EAL: Support maximum 128 logical core(s) by configuration.
EAL: Detected 4 lcore(s)
EAL: No free hugepages reported in hugepages-1048576kB
EAL: Module /sys/module/vfio_iommu_type1 not found! error 2 (No such file or directory)
EAL: VFIO modules not all loaded, skip VFIO support...
EAL: Setting up physically contiguous memory...
EAL: Ask a virtual area of 0x26600000 bytes
EAL: Virtual area found at 0x7f0cab600000 (size = 0x26600000)
EAL: Ask a virtual area of 0x84200000 bytes
EAL: Virtual area found at 0x7f0c27200000 (size = 0x84200000)
EAL: Ask a virtual area of 0x200000 bytes
EAL: Virtual area found at 0x7f0c26e00000 (size = 0x200000)
EAL: Ask a virtual area of 0x200000 bytes
EAL: Virtual area found at 0x7f0c26a00000 (size = 0x200000)
EAL: Ask a virtual area of 0x155000000 bytes
EAL: Virtual area found at 0x7f0ad1800000 (size = 0x155000000)
EAL: Ask a virtual area of 0x200000 bytes
EAL: Virtual area found at 0x7f0ad1400000 (size = 0x200000)
EAL: Ask a virtual area of 0x200000 bytes
EAL: Virtual area found at 0x7f0ad1000000 (size = 0x200000)
EAL: Requesting 4096 pages of size 2MB from socket 0
EAL: TSC frequency is ~2494222 KHz
EAL: WARNING: cpu flags constant_tsc=yes nonstop_tsc=no -> using unreliable clock cycles !
EAL: Master lcore 0 is ready (tid=d5cb2b00;cpuset=[0])
EAL: PCI device 0000:00:04.0 on NUMA socket -1
EAL:   probe driver: 1af4:1000 rte_virtio_pmd
PMD: device not available for DPDK (in use by kernel)
EAL: Error - exiting with code: 1
  Cause: Requested device 0000:00:04.0 cannot be used</pre>

ovs-vswitchd 进程启动失败。0000:00:04.0 是 eth0 的 pci 号，猜测 ovs-vswitchd 启动时，检索了所有的网卡。尝试将 eth0 也改成 DPDK 驱动。由于 ovs-vswitch 进程不启动，无法通过 external 登录节点，因此只能通过 vnc 进入。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="634" height="255" src="https://maxwellii.com/wp-content/uploads/2023/11/1.png?w=634" alt="" class="wp-image-181" srcset="http://47.84.100.47/wp-content/uploads/2023/11/1.png 634w, http://47.84.100.47/wp-content/uploads/2023/11/1-300x121.png 300w" sizes="(max-width: 634px) 100vw, 634px" /></figure>

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="823" height="596" src="https://maxwellii.com/wp-content/uploads/2023/11/2.png?w=823" alt="" class="wp-image-182" srcset="http://47.84.100.47/wp-content/uploads/2023/11/2.png 823w, http://47.84.100.47/wp-content/uploads/2023/11/2-300x217.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/2-768x556.png 768w" sizes="(max-width: 823px) 100vw, 823px" /></figure>

更加证实了我的猜想，再将 eth1 也改成 DPDK 驱动，尝试启动 ovs-vswitchd。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="626" height="271" src="https://maxwellii.com/wp-content/uploads/2023/11/3.png?w=626" alt="" class="wp-image-184" srcset="http://47.84.100.47/wp-content/uploads/2023/11/3.png 626w, http://47.84.100.47/wp-content/uploads/2023/11/3-300x130.png 300w" sizes="(max-width: 626px) 100vw, 626px" /></figure>

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1008" height="757" src="https://maxwellii.com/wp-content/uploads/2023/11/4.png?w=1008" alt="" class="wp-image-185" srcset="http://47.84.100.47/wp-content/uploads/2023/11/4.png 1008w, http://47.84.100.47/wp-content/uploads/2023/11/4-300x225.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/4-768x577.png 768w" sizes="(max-width: 1008px) 100vw, 1008px" /></figure>

重新在 /etc/default/openvswitch-switch 文件内打开 DPDK_OPTS 配置，openvswtich 服务能够正常重启。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1023" height="259" src="https://maxwellii.com/wp-content/uploads/2023/11/5.png?w=1023" alt="" class="wp-image-187" srcset="http://47.84.100.47/wp-content/uploads/2023/11/5.png 1023w, http://47.84.100.47/wp-content/uploads/2023/11/5-300x76.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/5-768x194.png 768w" sizes="(max-width: 1023px) 100vw, 1023px" /></figure>

至此，可以推断出 ovs-vswitchd 进程无法启动与 DPDK_OPTS 无关，而是检索了其他非 DPDK 网卡。因此，需要将非 DPDK 网卡加入一个黑名单中，防止 ovs-vswitchd 检索。谷歌后找到了[官方文档](http://dpdk.org/doc/guides-16.11/testpmd_app_ug/run_app.html)中关于白名单的相关说明：

<blockquote class="wp-block-quote is-layout-flow wp-block-quote-is-layout-flow">
-b, –pci-blacklist domain:bus:devid.func

Blacklist a PCI devise to prevent EAL from using it. Multiple -b options are allowed.

-w, –pci-whitelist domain:bus:devid:func

Add a PCI device in white list.

重新配置了一个计算节点，在 DPDK_OPTS 中加入 -w 0000:00:06.0，openvswitch 成功启动，此问题解决。

## **2. 两台计算节点上的实例无法 ping 通**

在控制节点上，给 flavor 加上大页配置，然后利用此 flavor 在两个计算节点上各起一个实例。实例无法连接 metadata，ip 无法分配，只能通过 vnc 进入后手动分配 ip，但是配上 ip 之后两个实例无法 ping 通。起实例前期准备如下：

<pre class="wp-block-syntaxhighlighter-code"># Source RC 文件
$ source /opt/admin-openrc.sh
# 上传 image
$ wget 10.1.0.12/image/cirros-0.3.3-x86_64-disk.img
$ glance image-create --name "cirros" --file cirros-0.3.3-x86_64-disk.img --disk-format qcow2 --container-format bare
# 配置安全组
$ nova secgroup-add-rule default icmp -1 -1 0.0.0.0/0
$ nova secgroup-add-rule default tcp 22 22 0.0.0.0/0
# 创建 demo-net
$ neutron net-create demo-net
$ neutron subnet-create \
    --name demo-subnet \
    --gateway 10.10.10.1 \
    --dns-nameserver 8.8.8.8 \
    demo-net 10.10.10.0/24
$ neutron router-create demo-router
$ neutron router-interface-add demo-router demo-subnet
$ neutron router-gateway-set demo-router ext-net
# 在 br-physnet2 上创建 provider 网络
$ neutron net-create dpdk-net \
    --provider:network_type flat \
    --provider:physical_network physnet2 \
    --router:external "False"
$ neutron subnet-create \
    --name dpdk-subnet \
    --disable-dhcp \
    --gateway 11.11.11.1 \
    --allocation-pool \
    start=11.11.11.10,end=11.11.11.100 \
    dpdk-net 11.11.11.0/24</pre>

在这里描述一下网络环境。OpenStack 网络如下：

<pre class="wp-block-syntaxhighlighter-code">$ neutron net-list
+--------------------------------------+----------+-------------------------------------------------------+
| id                                   | name     | subnets                                               |
+--------------------------------------+----------+-------------------------------------------------------+
| 3a51f801-9095-43f7-b8f9-29f9fa9bf4cf | ext-net  | 5bc900d3-6896-4765-98d1-5b8b3126100b 192.168.116.0/24 |
| 5d20fee2-fbaf-46cb-bd43-43869861fa30 | dpdk-net | 98a672f8-fd7f-4c6b-bdff-68898c953ef4 11.11.11.0/24    |
| b2ab0001-3877-4cb1-919f-c167c5389c03 | demo-net | 37491d05-041f-4a62-b1c3-2a3f23d383ba 10.10.10.0/24    |
+--------------------------------------+----------+-------------------------------------------------------+</pre>

虚拟部署组网图如下所示：

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1686" height="1235" src="https://maxwellii.com/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe.jpg?w=1024" alt="" class="wp-image-145" srcset="http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe.jpg 1686w, http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe-300x220.jpg 300w, http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe-1024x750.jpg 1024w, http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe-768x563.jpg 768w, http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe-1536x1125.jpg 1536w" sizes="(max-width: 1686px) 100vw, 1686px" /></figure>

ext-net 建在 br-prv 上，br-prv 是 eth1 上的 ovs 网桥，为正常网络。dpdk-net 建在 br-physnet2 上，br-physnet2 是 eth2 上的 ovs 网桥，进行了 DPDK 配置，上图中没有画出。由于此前一些 DPDK 专家告诉我，起 DPDK 虚拟机需要两个网络，一个作为管理网络，另外一个作为 DPDK 网络。因此我在 br-prv 和 br-physnet2 上各建了一个网络，这里存在问题，将在下一个问题中详细展开。

<pre class="wp-block-syntaxhighlighter-code">$ openstack server create \
    --flavor m1.tiny \
    --image cirros \
    --security-group default \
    --nic net-id=$(neutron net-list | grep demo-net | awk '{print $2}') \
    --nic net-id=$(neutron net-list | grep dpdk-net | awk '{print $2}') \
    --availability-zone nova:host4 demo1
$ openstack server create \
    --flavor m1.tiny \
    --image cirros \
    --security-group default \
    --nic net-id=$(neutron net-list | grep demo-net | awk '{print $2}') \
    --nic net-id=$(neutron net-list | grep dpdk-net | awk '{print $2}') \
    --availability-zone nova:host5 demo2</pre>

通过 VNC 进入实例，配上 ip 发现两个网络都无法 ping 通。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="559" height="230" src="https://maxwellii.com/wp-content/uploads/2023/11/6.png?w=559" alt="" class="wp-image-191" srcset="http://47.84.100.47/wp-content/uploads/2023/11/6.png 559w, http://47.84.100.47/wp-content/uploads/2023/11/6-300x123.png 300w" sizes="(max-width: 559px) 100vw, 559px" /></figure>

为了验证是实例的问题还是 DPDK 网络的问题，分别在两个计算节点上用 libvirt 各起一台虚拟机。陈帅发给我一份 XMl 文件，该文件可以使用 DPDK 网络，如下所示：

<pre class="wp-block-syntaxhighlighter-code"><domain type='kvm'>
  <name>vhost1</name>
  <memory unit='KiB'>16777216</memory>
  <currentMemory unit='KiB'>16777216</currentMemory>
  <memoryBacking>
    <hugepages>
      <page size='2048' unit='KiB' nodeset='0'/>
    </hugepages>
  </memoryBacking>
  <vcpu placement='static'>4</vcpu>
  <cputune>
    <vcpupin vcpu='0' cpuset='0-3'/>
    <vcpupin vcpu='1' cpuset='0-3'/>
    <vcpupin vcpu='2' cpuset='0-3'/>
    <vcpupin vcpu='3' cpuset='0-3'/>
    <emulatorpin cpuset='0-3'/>
  </cputune>
  <cpu>
    <topology sockets='4' cores='1' threads='1'/>
    <numa>
      <cell id='0' cpus='0-3' memory='1048576' memAccess='shared'/>
    </numa>
  </cpu>
  <numatune>
    <memory mode='strict' nodeset='0'/>
    <memnode cellid='0' mode='strict' nodeset='0'/>
  </numatune>
  <os>
    <type arch='x86_64' machine='pc-i440fx-trusty'>hvm</type>
    <boot dev='hd'/>
    <boot dev='network'/>
    <bios useserial='yes' rebootTimeout='0'/>
  </os>
  <features>
    <acpi/>
    <apic/>
    <pae/>
  </features>
  <clock offset='utc'/>
  <on_poweroff>destroy</on_poweroff>
  <on_reboot>restart</on_reboot>
  <on_crash>restart</on_crash>
  <devices>
    <disk type='file' device='disk'>
      <driver name='qemu' type='qcow2'/>
      <source file='/home/cirros1.img'/>
      <target dev='vda' bus='virtio'/>
      <alias name='virtio-disk0'/>
      <address type='pci' domain='0x0000' bus='0x00' slot='0x04' function='0x0'/>
    </disk>
    <controller type='usb' index='0'>
      <address type='pci' domain='0x0000' bus='0x00' slot='0x01' function='0x2'/>
    </controller>
    <controller type='pci' index='0' model='pci-root'/>
    <interface type='vhostuser'>
      <mac address='00:00:00:00:00:01'/>
      <source type='unix' path='/run/openvswitch/vhost-user1' mode='client'/>
      <model type='virtio'/>
    </interface>
    <serial type='pty'>
      <target port='0'/>
    </serial>
    <console type='pty'>
      <target type='serial' port='0'/>
    </console>
    <input type='mouse' bus='ps2'/>
    <input type='keyboard' bus='ps2'/>
    <graphics type='vnc' port='-1' autoport='yes' listen='0.0.0.0'>
      <listen type='address' address='0.0.0.0'/>
    </graphics>
    <video>
      <model type='cirrus' vram='9216' heads='1'/>
      <address type='pci' domain='0x0000' bus='0x00' slot='0x02' function='0x0'/>
    </video>
    <memballoon model='virtio'>
      <address type='pci' domain='0x0000' bus='0x00' slot='0x07' function='0x0'/>
    </memballoon>
  </devices>
</domain></pre>

利用此 XML 在两个计算节点上各起一台虚拟机（注意修改 mac 地址等信息），并且直接将 vhost-user 口建在 br-int 上。

<pre class="wp-block-syntaxhighlighter-code">$ ovs-vsctl add-port br-int vhost-user1 -- set Interface vhost-user1 type=dpdkvhostuser
$ ovs-vsctl list-ports br-int

int-br-physnet2
int-br-prv
patch-tun
vhost-user1
vhud51eb3cf-3f
vhueb25bb23-6d</pre>

其中 vhu*** 为 nova 起虚拟机时所建立的端口。

<pre class="wp-block-syntaxhighlighter-code">$ virsh define vhost1.xml 
$ virsh start vhost1</pre>

在另一台计算节点上也起一台虚拟机，然后通过 vnc 登录后，给两台虚拟机配上 ip，发现能够互相 ping 通。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="472" height="133" src="https://maxwellii.com/wp-content/uploads/2023/11/7.png?w=472" alt="" class="wp-image-193" srcset="http://47.84.100.47/wp-content/uploads/2023/11/7.png 472w, http://47.84.100.47/wp-content/uploads/2023/11/7-300x85.png 300w" sizes="(max-width: 472px) 100vw, 472px" /></figure>

由此可见，br-int 以及 DPDK 网络并没有问题。通过 Beyond Compare 对比陈帅给我的 XML 文件和 nova 起实例的 XML，大致猜测实例没有配置 Hugepage 和 NUMA。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="2178" height="590" src="https://maxwellii.com/wp-content/uploads/2023/11/8.png?w=1024" alt="" class="wp-image-195" srcset="http://47.84.100.47/wp-content/uploads/2023/11/8.png 2178w, http://47.84.100.47/wp-content/uploads/2023/11/8-300x81.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/8-1024x277.png 1024w, http://47.84.100.47/wp-content/uploads/2023/11/8-768x208.png 768w, http://47.84.100.47/wp-content/uploads/2023/11/8-1536x416.png 1536w, http://47.84.100.47/wp-content/uploads/2023/11/8-2048x555.png 2048w" sizes="(max-width: 2178px) 100vw, 2178px" /></figure>

配置 flavor 后重新起实例，DPDK 网络成功 ping 通。

<pre class="wp-block-syntaxhighlighter-code">$ openstack flavor create  m1.tiny_huge --ram 512 --disk 1 --vcpus 1
$ openstack flavor set \
    --property hw:mem_page_size=large \
    --property hw:cpu_policy=dedicated \
    --property hw:cpu_thread_policy=require \
    --property hw:numa_mempolicy=preferred \
    --property hw:numa_nodes=1 \
    --property hw:numa_cpus.0=0 \
    --property hw:numa_mem.0=512 \
    m1.tiny_huge</pre>

## **3. 实例无法连接 metadate**

DPDK 网络能够 ping 通，但是普通网络仍旧无法 ping 通，也无法通过 br-tun 连接到 metadata。仔细检查发现，计算节点的 br-int 网桥上只有 DPDK 类型的端口，没有 qvo 口。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1006" height="815" src="https://maxwellii.com/wp-content/uploads/2023/11/9.png?w=1006" alt="" class="wp-image-197" srcset="http://47.84.100.47/wp-content/uploads/2023/11/9.png 1006w, http://47.84.100.47/wp-content/uploads/2023/11/9-300x243.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/9-768x622.png 768w" sizes="(max-width: 1006px) 100vw, 1006px" /></figure>

原本期望实例链接 br-int 网桥的两个口，portA 作为 qvo 口，流量通过 br-tun 前往控制节点，portB 作为 DPDK 口，流量通过 br-physnet2 前往外部网络。然而事与愿违，在 br-int 上的两个口均为 DPDK 口，并且在计算节点上抓流表，发现所有流量都前往 br-physnet2。

<pre class="wp-block-syntaxhighlighter-code">...
    <interface type='vhostuser'>
      <mac address='fa:16:3e:b2:e5:a0'/>
      <source type='unix' path='/var/run/openvswitch/vhua47ab91a-0c' mode='client'/>
      <model type='virtio'/>
      <alias name='net0'/>
      <address type='pci' domain='0x0000' bus='0x00' slot='0x03' function='0x0'/>
    </interface>
    <interface type='vhostuser'>
      <mac address='fa:16:3e:34:19:fe'/>
      <source type='unix' path='/var/run/openvswitch/vhucd29dabe-61' mode='client'/>
      <model type='virtio'/>
      <alias name='net1'/>
      <address type='pci' domain='0x0000' bus='0x00' slot='0x04' function='0x0'/>
    </interface>
...</pre>

通过查看实例的 XML 文件得知：port vhua47ab91a-0c 为 demo-net 创建，port vhucd29dabe-61 为 dpdk-net 创建。查看 br-int 信息可知：port vhua47ab91a-0c 编号为 16，port vhucd29dabe-61 编号为 17。

<pre class="wp-block-syntaxhighlighter-code">$ ovs-ofctl show br-int
OFPT_FEATURES_REPLY (xid=0x2): dpid:00005e308d6dad4f
n_tables:254, n_buffers:256
capabilities: FLOW_STATS TABLE_STATS PORT_STATS QUEUE_STATS ARP_MATCH_IP
actions: output enqueue set_vlan_vid set_vlan_pcp strip_vlan mod_dl_src mod_dl_dst mod_nw_src mod_nw_dst mod_nw_tos mod_tp_src mod_tp_dst
 1(int-br-physnet2): addr:be:27:6b:88:f0:af
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 2(int-br-prv): addr:9e:1c:f7:a5:4f:6a
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 3(patch-tun): addr:3e:2d:ee:8a:b9:f9
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 16(vhua47ab91a-0c): addr:00:00:00:00:00:00
     config:     PORT_DOWN
     state:      LINK_DOWN
     speed: 0 Mbps now, 0 Mbps max
 17(vhucd29dabe-61): addr:00:00:00:00:00:00
     config:     PORT_DOWN
     state:      LINK_DOWN
     speed: 0 Mbps now, 0 Mbps max
 LOCAL(br-int): addr:5e:30:8d:6d:ad:4f
     config:     PORT_DOWN
     state:      LINK_DOWN
     current:    10MB-FD COPPER
     speed: 10 Mbps now, 0 Mbps max
OFPT_GET_CONFIG_REPLY (xid=0x4): frags=normal miss_send_len=0</pre>

通过 vnc 进入实例，配上 ip 之后 ping 另一台实例，在 br-int 和 br-physnet2 上连续抓流表，发现 port16 出来的包在 br-physnet2 上被 drop。以下只记入了 port16 相关信息，port17 流量正常。

<pre class="wp-block-syntaxhighlighter-code">$ ovs-ofctl dump-flows br-int
...
 cookie=0xb181a97b01fa1a9f, duration=378.439s, table=0, n_packets=0, n_bytes=0, idle_age=378, priority=10,arp,in_port=16 actions=resubmit(,24)

 cookie=0xb181a97b01fa1a9f, duration=378.551s, table=24, n_packets=0, n_bytes=0, idle_age=378, priority=2,arp,in_port=16,arp_spa=10.10.10.3 actions=resubmit(,25)

 cookie=0xb181a97b01fa1a9f, duration=379.109s, table=25, n_packets=9, n_bytes=1464, idle_age=235, priority=2,in_port=16,dl_src=fa:16:3e:b2:e5:a0 actions=NORMAL
...

$ ovs-ofctl dump-flows br-int
...
 cookie=0xb181a97b01fa1a9f, duration=395.872s, table=0, n_packets=6, n_bytes=252, idle_age=0, priority=10,arp,in_port=16 actions=resubmit(,24)

 cookie=0xb181a97b01fa1a9f, duration=395.984s, table=24, n_packets=6, n_bytes=252, idle_age=0, priority=2,arp,in_port=16,arp_spa=10.10.10.3 actions=resubmit(,25)

 cookie=0xb181a97b01fa1a9f, duration=396.542s, table=25, n_packets=15, n_bytes=1716, idle_age=0, priority=2,in_port=16,dl_src=fa:16:3e:b2:e5:a0 actions=NORMAL
...</pre>

从以上信息中可以看出，port16 的包在 br-int 上正常，同时在 br-physnet2 上抓流表，得到了相同数量的变化。因此，port16 的包进入了 br-physnet2，并且在 br-physnet2 上被 drop。

<pre class="wp-block-syntaxhighlighter-code">$ ovs-ofctl dump-flows br-physnet2
...
 cookie=0xac17fb961e721d6e, duration=32311.941s, table=0, n_packets=188, n_bytes=14448, idle_age=16, priority=2,in_port=2 actions=drop
...

$ ovs-ofctl dump-flows br-physnet2
...
 cookie=0xac17fb961e721d6e, duration=32324.557s, table=0, n_packets=194, n_bytes=14738, idle_age=1, priority=2,in_port=2 actions=drop
...

$ ovs-ofctl show br-physnet2
OFPT_FEATURES_REPLY (xid=0x2): dpid:00001683ed7b0446
n_tables:254, n_buffers:256
capabilities: FLOW_STATS TABLE_STATS PORT_STATS QUEUE_STATS ARP_MATCH_IP
actions: output enqueue set_vlan_vid set_vlan_pcp strip_vlan mod_dl_src mod_dl_dst mod_nw_src mod_nw_dst mod_nw_tos mod_tp_src mod_tp_dst
 1(dpdk0): addr:52:54:00:27:96:c7
     config:     0
     state:      0
     current:    10GB-FD
     supported:  10MB-HD 100MB-HD 1GB-HD 10GB-FD COPPER FIBER AUTO_PAUSE_ASYM
     speed: 10000 Mbps now, 10000 Mbps max
 2(phy-br-physnet2): addr:02:1d:5b:ba:3e:2e
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 LOCAL(br-physnet2): addr:16:83:ed:7b:04:46
     config:     PORT_DOWN
     state:      LINK_DOWN
     current:    10MB-FD COPPER
     speed: 10 Mbps now, 0 Mbps max
OFPT_GET_CONFIG_REPLY (xid=0x4): frags=normal miss_send_len=0</pre>

br-physnet2 上 port2 的包是从 br-int 上过来的，直接被 drop。这也是应为上图中 portA 口的类型也为 DPDK，当 portA 出来的包进入 br-physnet2 时，br-physnet2 并不认识它，因此直接 drop。另外，如果需要 portA 建为 qvo 类型，需要修改 nova 和 neutron 的代码，显然不切实际。

因此，期望实例能够连接 metadata，只能直接通过外部网络。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1006" height="815" src="https://maxwellii.com/wp-content/uploads/2023/11/10.png?w=1006" alt="" class="wp-image-199" srcset="http://47.84.100.47/wp-content/uploads/2023/11/10.png 1006w, http://47.84.100.47/wp-content/uploads/2023/11/10-300x243.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/10-768x622.png 768w" sizes="(max-width: 1006px) 100vw, 1006px" /></figure>

由于此前建立 dpdk-net 并没有开启 dhcp，删除网络后重新建立，并且在 /etc/neutron/dhcp_agent.in 配置文件中配置 metadata 相关配置项为 True，重启 neutron 服务并重建 dpdk-net，拉起实例之后可以连接上 metadata。

<pre class="wp-block-syntaxhighlighter-code">$ crudini --set /etc/neutron/dhcp_agent.ini DEFAULT enable_isolated_metadata True
$ crudini --set /etc/neutron/dhcp_agent.ini DEFAULT force_metadata True
$ crudini --set /etc/neutron/dhcp_agent.ini DEFAULT enable_metadata_network True
$ for i in $(cat /opt/service | grep neutron); do service $i restart; done

$ neutron net-create dpdk-net \
    --provider:network_type flat \
    --provider:physical_network physnet2 \
    --router:external "False"

$ neutron subnet-create \
    --name dpdk-subnet \
    --gateway 22.22.22.1 \
    dpdk-net 22.22.22.0/24

$ openstack server create \
    --flavor m1.tiny_huge \
    --image cirros \
    --nic net-id=$(neutron net-list | grep dpdk-net | awk '{print $2}') \
    --availability-zone nova:host4 demo1</pre>

查看实例的 console log，发现实例已经连上 metadata 并且配上了 ip，可以直接通过名字空间登录。

## **总结**

调试 DPDK 前前后后花了大半个月，中间也碰到了不少问题，这里只记录了比较有价值的三个。在调试完后为了写这篇总结，复现问题也花了不少精力。但整个过程调试下来，对大页、DPDK 有了较为系统的了解，收获颇多。

## **参考网页**

https://docs.openstack.org/admin-guide/compute-flavors.html

https://bugs.launchpad.net/ubuntu/+source/dpdk/+bug/1546565

http://blog.csdn.net/quqi99/article/details/51087955

https://bugs.launchpad.net/ubuntu/+source/openvswitch-dpdk/+bug/1547463

https://github.com/openvswitch/ovs/blob/master/Documentation/intro/install/dpdk.rst

http://feisky.xyz/sdn/dpdk/index.html

https://access.redhat.com/documentation/en-us/red_hat_openstack_platform/10/html-single/network_functions_virtualization_planning_guide/#ch-managing-deployments

https://access.redhat.com/documentation/en-us/red_hat_openstack_platform/10/html/network_functions_virtualization_configuration_guide/part-dpdk-configure

http://dpdk.org/doc/guides-16.11/testpmd_app_ug/run_app.html

http://sysight.com/index.php?qa=17&qa_1=hugepage的优势与使用
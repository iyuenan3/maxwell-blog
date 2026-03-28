---
title: "OpenStack Neutron 网络实现简析"
date: 2017-04-14T10:31:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [8, 10, 18, 19, 20, 21, 24]
url: http://47.84.100.47/?p=143
---


> 在西安出差这一段时间，对 OpenStack 的网络虚拟化有了一些了解。在阅读《…

---

<ul class="wp-block-list">
- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#环境">环境</a>

- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#网络实现">网络实现</a>

<li><a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#计算节点">计算节点</a>
<ol class="wp-block-list">
- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#qbr">qbr</a>

- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#br-int">br-int</a>

- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#br-tun">br-tun</a>

</li>

<li><a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#网络节点-控制节点">网络节点（控制节点）</a>
<ol class="wp-block-list">
- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#br-tun">br-tun</a>

- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#br-int">br-int</a>

- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#br-prv">br-prv</a>

</li>

<li><a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#名字空间">名字空间</a>
<ol class="wp-block-list">
- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#dhcp-服务">DHCP 服务</a>

- <a class="wp-block-table-of-contents__entry" href="https://limaxwell93.wordpress.com/?p=143#router-服务">Router 服务</a>

</li>

在西安出差这一段时间，对 OpenStack 的网络虚拟化有了一些了解。在阅读[《深入理解 Neutron — OpenStack 网络实现》](https://www.gitbook.com/book/yeasy/openstack_understand_neutron/details)之后，对 OpenStack 进行简单的网络分析总结。

## **环境**

此篇博客使用 Ubuntu xenial Newton 虚拟部署环境，一个控制节点 host1，两个存储节点 host2 host3，两个计算节点 host4 host5，网络节点与控制节点部署在一起，本文不讨论存储节点的网络配置。环境上建立了 ext-net 网络和 demo-net 网络，利用 demo-net 起了三个实例，并且分配了 floating ip。demo1 demo3 在 host4 上，demo2 在 host5 上。

虚拟部署网络结构如下图所示：

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1686" height="1235" src="https://maxwellii.com/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe.jpg?w=1024" alt="" class="wp-image-145" srcset="http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe.jpg 1686w, http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe-300x220.jpg 300w, http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe-1024x750.jpg 1024w, http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe-768x563.jpg 768w, http://47.84.100.47/wp-content/uploads/2023/11/e8999ae68b9fe983a8e7bdb2e7bb84e7bd91e59bbe-1536x1125.jpg 1536w" sizes="(max-width: 1686px) 100vw, 1686px" /></figure>

基本信息如下：

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# nova list
+--------------------------------------+-------+--------+------------+-------------+---------------------------------------+
| ID                                   | Name  | Status | Task State | Power State | Networks                              |
+--------------------------------------+-------+--------+------------+-------------+---------------------------------------+
| d88726c6-99a2-4d73-b041-7366aed31d98 | demo1 | ACTIVE | -          | Running     | demo-net=10.10.10.10, 192.168.116.224 |
| ded0d9d9-6739-41ba-b43c-599af217ad2d | demo2 | ACTIVE | -          | Running     | demo-net=10.10.10.12, 192.168.116.233 |
| ddcadd1f-ad65-41c4-aeab-5c54b4c61675 | demo3 | ACTIVE | -          | Running     | demo-net=10.10.10.13, 192.168.116.226 |
+--------------------------------------+-------+--------+------------+-------------+---------------------------------------+

root@host1:~# neutron net-list
+--------------------------------------+----------+-------------------------------------------------------+
| id                                   | name     | subnets                                               |
+--------------------------------------+----------+-------------------------------------------------------+
| 773bcf25-d146-41ac-b4b5-d6b3c8bf65d8 | ext-net  | 0553d050-379f-42fd-a11b-1d229913e563 192.168.116.0/24 |
| d60e4d79-bc10-4470-9434-297ace28ca84 | demo-net | 4be2b598-a9aa-40df-af3b-812a1df0bf80 10.10.10.0/24    |
+--------------------------------------+----------+-------------------------------------------------------+

root@host1:~# neutron subnet-list
+--------------------------------------+-------------+------------------+--------------------------------------------------------+
| id                                   | name        | cidr             | allocation_pools                                       |
+--------------------------------------+-------------+------------------+--------------------------------------------------------+
| 0553d050-379f-42fd-a11b-1d229913e563 | ext-subnet  | 192.168.116.0/24 | {"start": "192.168.116.223", "end": "192.168.116.253"} |
| 4be2b598-a9aa-40df-af3b-812a1df0bf80 | demo-subnet | 10.10.10.0/24    | {"start": "10.10.10.2", "end": "10.10.10.254"}         |
+--------------------------------------+-------------+------------------+--------------------------------------------------------+</pre>

## **网络实现**

OpenStack 中网络实现包括 VLAN、GRE、VXLAN 等模式，Compass4NFV 部署的 OpenStack 网络实现使用 VXLAN 模式，其余模式也类似。基本结构如下图所示：

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1441" height="697" src="https://maxwellii.com/wp-content/uploads/2023/11/neutron.jpg?w=1024" alt="" class="wp-image-147" srcset="http://47.84.100.47/wp-content/uploads/2023/11/neutron.jpg 1441w, http://47.84.100.47/wp-content/uploads/2023/11/neutron-300x145.jpg 300w, http://47.84.100.47/wp-content/uploads/2023/11/neutron-1024x495.jpg 1024w, http://47.84.100.47/wp-content/uploads/2023/11/neutron-768x371.jpg 768w" sizes="(max-width: 1441px) 100vw, 1441px" /></figure>

## **计算节点**

计算节点主要包含两个 ovs 网桥：集成网桥 br-int、隧道网桥 br-tun，以及每个实例都会有自己的 linux 网桥 qbr 主要作为安全组使用。

### **qbr**

通过对应实例的 dumpxml 可以找到实例连接到的 linux 网桥。以 demo2 为例：

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# nova show demo2 | grep instance_name
| OS-EXT-SRV-ATTR:instance_name        | instance-00000002                                        |

root@host5:~# virsh dumpxml instance-00000002
...
    <interface type='bridge'>
      <mac address='fa:16:3e:50:09:42'/>
      <source bridge='qbrbb09acdf-a4'/>
      <target dev='tapbb09acdf-a4'/>
      <model type='virtio'/>
      <alias name='net0'/>
      <address type='pci' domain='0x0000' bus='0x00' slot='0x03' function='0x0'/>
    </interface>
...

root@host5:~# brctl show
bridge name	bridge id		STP enabled	interfaces
qbrbb09acdf-a4		8000.0a6dbff5ddde	no		qvbbb09acdf-a4
							tapbb09acdf-a4
virbr0		8000.525400f0397c	yes		virbr0-nic</pre>

可见 demo2 通过 tap 口连接到 qbr linux 网桥。而 linux 网桥通过 qvb 接口连接到 br-int。

### **br-int**

集成网桥 br-int作为二层交换机使用，无论下面使用哪种技术实现虚拟化，都不会受到影响。

<pre class="wp-block-syntaxhighlighter-code">root@host5:~# ovs-vsctl show
...
    Bridge br-int
        Controller "tcp:127.0.0.1:6633"
            is_connected: true
        fail_mode: secure
        Port br-int
            Interface br-int
                type: internal
        Port patch-tun
            Interface patch-tun
                type: patch
                options: {peer=patch-int}
        Port "qvobb09acdf-a4"
            tag: 1
            Interface "qvobb09acdf-a4"
        Port int-br-prv
            Interface int-br-prv
                type: patch
                options: {peer=phy-br-prv}
...</pre>

可以看到 br-int 上有多个连接接口，主要包括以下几个接口：

<ul class="wp-block-list">
- qvo 接口，连接 Linux 网桥。qvo 接口会给每个网络分配一个内部 vlan 号，因为这里两个实例起在同一个网络上，所以 tag 值都为 1。

- patch-tun 接口，连接到 br-tun。

在 Juno 版本之前，所有流量都需要通过网络节点转发，这给网络节点带来了很大的压力。因此在 Juno 版本之后启用了 DVR （分布式路由）特性，允许东西向流量和带有 floating ip 的南北向流量可以直接从计算节点的 br-prv 出去。Compass4NFV 没有启用 DVR 特性，关于 DVR 特性这里暂时不做展开。

<pre class="wp-block-syntaxhighlighter-code">root@host5:~# ovs-ofctl dump-flows br-int
NXST_FLOW reply (xid=0x4):
 cookie=0x85b3d27713d4f435, duration=5437.237s, table=0, n_packets=0, n_bytes=0, idle_age=5437, priority=10,icmp6,in_port=3,icmp_type=136 actions=resubmit(,24)
 cookie=0x85b3d27713d4f435, duration=5437.232s, table=0, n_packets=1, n_bytes=42, idle_age=5430, priority=10,arp,in_port=3 actions=resubmit(,24)
 cookie=0x85b3d27713d4f435, duration=19854.128s, table=0, n_packets=19999, n_bytes=1080954, idle_age=0, priority=2,in_port=1 actions=drop
 cookie=0x85b3d27713d4f435, duration=5437.243s, table=0, n_packets=107, n_bytes=10709, idle_age=5420, priority=9,in_port=3 actions=resubmit(,25)
 cookie=0x85b3d27713d4f435, duration=19854.886s, table=0, n_packets=84, n_bytes=9442, idle_age=5403, priority=0 actions=NORMAL
 cookie=0x85b3d27713d4f435, duration=19854.884s, table=23, n_packets=0, n_bytes=0, idle_age=19854, priority=0 actions=drop
 cookie=0x85b3d27713d4f435, duration=5437.240s, table=24, n_packets=0, n_bytes=0, idle_age=5437, priority=2,icmp6,in_port=3,icmp_type=136,nd_target=fe80::f816:3eff:fe50:942 actions=NORMAL
 cookie=0x85b3d27713d4f435, duration=5437.235s, table=24, n_packets=1, n_bytes=42, idle_age=5430, priority=2,arp,in_port=3,arp_spa=10.10.10.12 actions=resubmit(,25)
 cookie=0x85b3d27713d4f435, duration=19854.883s, table=24, n_packets=0, n_bytes=0, idle_age=19854, priority=0 actions=drop
 cookie=0x85b3d27713d4f435, duration=5437.248s, table=25, n_packets=107, n_bytes=10681, idle_age=5420, priority=2,in_port=3,dl_src=fa:16:3e:50:09:42 actions=NORMAL
 
root@host5:~# ovs-ofctl show br-int
OFPT_FEATURES_REPLY (xid=0x2): dpid:0000e27f47ffb148
n_tables:254, n_buffers:256
capabilities: FLOW_STATS TABLE_STATS PORT_STATS QUEUE_STATS ARP_MATCH_IP
actions: output enqueue set_vlan_vid set_vlan_pcp strip_vlan mod_dl_src mod_dl_dst mod_nw_src mod_nw_dst mod_nw_tos mod_tp_src mod_tp_dst
 1(int-br-prv): addr:7a:7b:34:bb:f5:4b
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 2(patch-tun): addr:72:cf:68:02:b0:f4
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 3(qvobb09acdf-a4): addr:ee:d4:71:35:31:3b
     config:     0
     state:      0
     current:    10GB-FD COPPER
     speed: 10000 Mbps now, 0 Mbps max
 LOCAL(br-int): addr:e2:7f:47:ff:b1:48
     config:     PORT_DOWN
     state:      LINK_DOWN
     speed: 0 Mbps now, 0 Mbps max
OFPT_GET_CONFIG_REPLY (xid=0x4): frags=normal miss_send_len=0</pre>

可以看到，table0 中对 in_port=3 的包重新提交到 table24 或者 table25 之后 NORMAL，而 table23 中所有包都直接丢弃。

### **br-tun**

<pre class="wp-block-syntaxhighlighter-code">root@host5:~# ovs-vsctl show
...
    Bridge br-tun
        Controller "tcp:127.0.0.1:6633"
            is_connected: true
        fail_mode: secure
        Port br-tun
            Interface br-tun
                type: internal
        Port "vxlan-ac100104"
            Interface "vxlan-ac100104"
                type: vxlan
                options: {df_default="true", in_key=flow, local_ip="172.16.1.5", out_key=flow, remote_ip="172.16.1.4"}
        Port patch-int
            Interface patch-int
                type: patch
                options: {peer=patch-tun}
        Port "vxlan-ac100101"
            Interface "vxlan-ac100101"
                type: vxlan
                options: {df_default="true", in_key=flow, local_ip="172.16.1.5", out_key=flow, remote_ip="172.16.1.1"}
...</pre>

在上面的 br-tun 网桥中，主要包括以下两个接口：

<ul class="wp-block-list">
- vxlan 接口，向其他节点发送包时候的 vxlan 隧道接口。

- patch-int 接口，和 br-int 上的 patch-tun 端口通过一条管道连接。

隧道网桥 br-tun 作为虚拟化层网桥，br-tun 会对内部过来的网包进行合理甄别，内部带正确 vlan tag 的包过来，从正确的 tunnel 丢出去；外部带正确 tunnel 的包进来，修改成对应的内部 vlan tag 再丢进来。具体规则如下图所示：

<figure class="wp-block-image size-large"><img decoding="async" src="https://maxwellii.com/wp-content/uploads/2023/11/ovs_rules_compute_br_tun.png?w=1024" alt="" class="wp-image-151" /></figure>

下面针对不同的 table 进行分析：

<pre class="wp-block-syntaxhighlighter-code">root@host5:~# ovs-ofctl show br-tun
OFPT_FEATURES_REPLY (xid=0x2): dpid:000056e643feb343
n_tables:254, n_buffers:256
capabilities: FLOW_STATS TABLE_STATS PORT_STATS QUEUE_STATS ARP_MATCH_IP
actions: output enqueue set_vlan_vid set_vlan_pcp strip_vlan mod_dl_src mod_dl_dst mod_nw_src mod_nw_dst mod_nw_tos mod_tp_src mod_tp_dst
 1(patch-int): addr:42:85:98:00:79:06
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 2(vxlan-ac100101): addr:6a:a7:02:4c:bd:88
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 3(vxlan-ac100104): addr:f6:8e:f8:4f:64:7d
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 LOCAL(br-tun): addr:56:e6:43:fe:b3:43
     config:     PORT_DOWN
     state:      LINK_DOWN
     speed: 0 Mbps now, 0 Mbps max
OFPT_GET_CONFIG_REPLY (xid=0x4): frags=normal miss_send_len=0</pre>

#### **table0**

<pre class="wp-block-syntaxhighlighter-code"> cookie=0xacf7091c4749e28a, duration=21993.420s, table=0, n_packets=113, n_bytes=11169, idle_age=7560, priority=1,in_port=1 actions=resubmit(,2)
 cookie=0xacf7091c4749e28a, duration=21992.342s, table=0, n_packets=86, n_bytes=9210, idle_age=7569, priority=1,in_port=2 actions=resubmit(,4)
 cookie=0xacf7091c4749e28a, duration=21992.039s, table=0, n_packets=18, n_bytes=2372, idle_age=7543, priority=1,in_port=3 actions=resubmit(,4)
 cookie=0xacf7091c4749e28a, duration=21993.418s, table=0, n_packets=0, n_bytes=0, idle_age=21993, priority=0 actions=drop</pre>

对于 in_port=1 的包，即从 patch-int 传进来的网包，提交给 table2 处理；对于 in_port=2 或者 in_port=3 的包，即从 vxlan 传进来的网包，提交给 table4 处理。即 table2 处理内部 VM 的包，table4 处理来自外面 vxlan 隧道的包。

#### **table2**

<pre class="wp-block-syntaxhighlighter-code"> cookie=0xacf7091c4749e28a, duration=21993.416s, table=2, n_packets=98, n_bytes=9495, idle_age=7569, priority=0,dl_dst=00:00:00:00:00:00/01:00:00:00:00:00 actions=resubmit(,20)
 cookie=0xacf7091c4749e28a, duration=21993.414s, table=2, n_packets=15, n_bytes=1674, idle_age=7560, priority=0,dl_dst=01:00:00:00:00:00/01:00:00:00:00:00 actions=resubmit(,22)</pre>

对于传入的单播包，丢给 table20 处理；多播和广播包，丢给 table22 包。

#### **table3**

<pre class="wp-block-syntaxhighlighter-code"> cookie=0xacf7091c4749e28a, duration=21993.412s, table=3, n_packets=0, n_bytes=0, idle_age=21993, priority=0 actions=drop</pre>

丢弃所有包。

#### **table4**

<pre class="wp-block-syntaxhighlighter-code"> cookie=0xacf7091c4749e28a, duration=7583.782s, table=4, n_packets=78, n_bytes=8954, idle_age=7543, priority=1,tun_id=0x40b actions=mod_vlan_vid:1,resubmit(,10)
 cookie=0xacf7091c4749e28a, duration=21993.411s, table=4, n_packets=26, n_bytes=2628, idle_age=7586, priority=0 actions=drop</pre>

匹配 tunnel 号，添加对应的 vlan tag，然后提交给 table10。

#### **table6**

<pre class="wp-block-syntaxhighlighter-code"> cookie=0xacf7091c4749e28a, duration=21993.409s, table=6, n_packets=0, n_bytes=0, idle_age=21993, priority=0 actions=drop</pre>

丢弃所有包。

#### **table10**

<pre class="wp-block-syntaxhighlighter-code"> cookie=0xacf7091c4749e28a, duration=21993.407s, table=10, n_packets=78, n_bytes=8954, idle_age=7543, priority=1 actions=learn(table=20,hard_timeout=300,priority=1,cookie=0xacf7091c4749e28a,NXM_OF_VLAN_TCI[0..11],NXM_OF_ETH_DST[]=NXM_OF_ETH_SRC[],load:0->NXM_OF_VLAN_TCI[],load:NXM_NX_TUN_ID[]->NXM_NX_TUN_ID[],output:OXM_OF_IN_PORT[]),output:1</pre>

table10 主要作用是学习从 tunnel 传入的包，往 table20 中添加对返程包的正常转发规则，并且通过 patch-int 丢给 br-int。table10 使用了 openvswitch 的 learn 动作，该动作能够根据处理的流来动态修改其它表中的规则。具体规则如下：

<ul class="wp-block-list">
- NXM_OF_VLAN_TCI[0..11]：匹配跟当前流同样的 VLAN 头，其中 NXM 是 Nicira Extensible Match 的缩写；

- NXM_OF_ETH_DST[]=NXM_OF_ETH_SRC[]：包的目的 mac 跟当前流的源 mac 匹配；

- load:0->NXM_OF_VLAN_TCI[]：将 vlan 号改为 0；

- load:NXM_NX_TUN_ID[]->NXM_NX_TUN_ID[]：将 tunnel 号改为当前的 tunnel 号；

- output:OXM_OF_IN_PORT[]：从当前入口发出。

#### **table20**

<pre class="wp-block-syntaxhighlighter-code"> cookie=0xacf7091c4749e28a, duration=7.784s, table=20, n_packets=23, n_bytes=3452, hard_timeout=300, idle_age=1, hard_age=1, priority=1,vlan_tci=0x0001/0x0fff,dl_dst=fa:16:3e:fe:6e:a8 actions=load:0->NXM_OF_VLAN_TCI[],load:0x40b->NXM_NX_TUN_ID[],output:2
 cookie=0xacf7091c4749e28a, duration=7.673s, table=20, n_packets=5, n_bytes=434, hard_timeout=300, idle_age=2, hard_age=2, priority=1,vlan_tci=0x0001/0x0fff,dl_dst=fa:16:3e:81:4a:a6 actions=load:0->NXM_OF_VLAN_TCI[],load:0x40b->NXM_NX_TUN_ID[],output:3
 cookie=0xacf7091c4749e28a, duration=80448.803s, table=20, n_packets=0, n_bytes=0, idle_age=65534, hard_age=65534, priority=0 actions=resubmit(,22)</pre>

前两条规则就是从 table10 学习后的结果，之前我在 demo2 实例内 ping 另一个计算节点上的 demo1。可以看到，对于 vlan tag 为 1，目标 mac 地址为 fa:16:3e:fe:6e:a6 的包，去掉 vlan tag（load:0->NXM_OF_VLAN_TCI[]），添加当时的 vxlan 号（load:0x40b->NXM_NX_TUN_ID[]），并从 tunnel 口发出去。

对于没有学习到规则的包，丢给 table22 处理。

#### **table22**

<pre class="wp-block-syntaxhighlighter-code"> cookie=0xacf7091c4749e28a, duration=66039.186s, table=22, n_packets=12, n_bytes=1312, idle_age=7, hard_age=65534, priority=1,dl_vlan=1 actions=strip_vlan,load:0x40b->NXM_NX_TUN_ID[],output:3,output:2
 cookie=0xacf7091c4749e28a, duration=80448.801s, table=22, n_packets=6, n_bytes=488, idle_age=65534, hard_age=65534, priority=0 actions=drop</pre>

table22 检查如果 vlan tag 正确，则去掉 vlan 头后从 tunnel 扔出去。

## **网络节点（控制节点）**

网络节点（Compass4NFV 将网络节点和控制节点部署在一起）担负网络服务任务，包括DHCP、路由和高级网络服务等。一般包括三个网桥：br-tun、br-int 和 br-prv。

### **br-tun**

隧道网桥 br-tun 与计算节点类似，作为虚拟化层网桥。

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ovs-vsctl show
...
    Bridge br-tun
        Controller "tcp:127.0.0.1:6633"
            is_connected: true
        fail_mode: secure
        Port br-tun
            Interface br-tun
                type: internal
        Port patch-int
            Interface patch-int
                type: patch
                options: {peer=patch-tun}
        Port "vxlan-ac100104"
            Interface "vxlan-ac100104"
                type: vxlan
                options: {df_default="true", in_key=flow, local_ip="172.16.1.1", out_key=flow, remote_ip="172.16.1.4"}
        Port "vxlan-ac100105"
            Interface "vxlan-ac100105"
                type: vxlan
                options: {df_default="true", in_key=flow, local_ip="172.16.1.1", out_key=flow, remote_ip="172.16.1.5"}
...</pre>

主要包括以下两个接口：

<ul class="wp-block-list">
- vxlan 接口，与其他节点的 vxlan 端口形成 tunnel。

- patch-int 接口，连接到 br-tun。

查看 br-tun 上的转发规则：

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ovs-ofctl dump-flows br-tun
NXST_FLOW reply (xid=0x4):
 cookie=0x946168d52f4f06d4, duration=86515.003s, table=0, n_packets=72600, n_bytes=3941480, idle_age=0, hard_age=65534, priority=1,in_port=1 actions=resubmit(,2)
 cookie=0x946168d52f4f06d4, duration=86209.803s, table=0, n_packets=135, n_bytes=14633, idle_age=5763, hard_age=65534, priority=1,in_port=2 actions=resubmit(,4)
 cookie=0x946168d52f4f06d4, duration=86209.495s, table=0, n_packets=224, n_bytes=22548, idle_age=28565, hard_age=65534, priority=1,in_port=3 actions=resubmit(,4)
 cookie=0x946168d52f4f06d4, duration=86515s, table=0, n_packets=0, n_bytes=0, idle_age=65534, hard_age=65534, priority=0 actions=drop
 cookie=0x946168d52f4f06d4, duration=86514.997s, table=2, n_packets=261, n_bytes=31232, idle_age=5763, hard_age=65534, priority=0,dl_dst=00:00:00:00:00:00/01:00:00:00:00:00 actions=resubmit(,20)
 cookie=0x946168d52f4f06d4, duration=86514.994s, table=2, n_packets=72339, n_bytes=3910248, idle_age=0, hard_age=65534, priority=0,dl_dst=01:00:00:00:00:00/01:00:00:00:00:00 actions=resubmit(,22)
 cookie=0x946168d52f4f06d4, duration=86514.992s, table=3, n_packets=0, n_bytes=0, idle_age=65534, hard_age=65534, priority=0 actions=drop
 cookie=0x946168d52f4f06d4, duration=71853.921s, table=4, n_packets=359, n_bytes=37181, idle_age=5763, hard_age=65534, priority=1,tun_id=0x40b actions=mod_vlan_vid:1,resubmit(,10)
 cookie=0x946168d52f4f06d4, duration=86514.988s, table=4, n_packets=0, n_bytes=0, idle_age=65534, hard_age=65534, priority=0 actions=drop
 cookie=0x946168d52f4f06d4, duration=86514.986s, table=6, n_packets=0, n_bytes=0, idle_age=65534, hard_age=65534, priority=0 actions=drop
 cookie=0x946168d52f4f06d4, duration=86514.983s, table=10, n_packets=359, n_bytes=37181, idle_age=5763, hard_age=65534, priority=1 actions=learn(table=20,hard_timeout=300,priority=1,cookie=0x946168d52f4f06d4,NXM_OF_VLAN_TCI[0..11],NXM_OF_ETH_DST[]=NXM_OF_ETH_SRC[],load:0->NXM_OF_VLAN_TCI[],load:NXM_NX_TUN_ID[]->NXM_NX_TUN_ID[],output:OXM_OF_IN_PORT[]),output:1
 cookie=0x946168d52f4f06d4, duration=86514.979s, table=20, n_packets=22, n_bytes=1868, idle_age=5769, hard_age=65534, priority=0 actions=resubmit(,22)
 cookie=0x946168d52f4f06d4, duration=71853.924s, table=22, n_packets=19, n_bytes=1586, idle_age=5769, hard_age=65534, priority=1,dl_vlan=1 actions=strip_vlan,load:0x40b->NXM_NX_TUN_ID[],output:3,output:2
 cookie=0x946168d52f4f06d4, duration=86514.977s, table=22, n_packets=72342, n_bytes=3910530, idle_age=0, hard_age=65534, priority=0 actions=drop

root@host1:~# ovs-ofctl show br-tun
OFPT_FEATURES_REPLY (xid=0x2): dpid:00000a4919379545
n_tables:254, n_buffers:256
capabilities: FLOW_STATS TABLE_STATS PORT_STATS QUEUE_STATS ARP_MATCH_IP
actions: output enqueue set_vlan_vid set_vlan_pcp strip_vlan mod_dl_src mod_dl_dst mod_nw_src mod_nw_dst mod_nw_tos mod_tp_src mod_tp_dst
 1(patch-int): addr:ce:27:3e:4f:8d:e7
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 2(vxlan-ac100105): addr:2a:86:c7:ba:09:c6
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 3(vxlan-ac100104): addr:22:25:b5:30:4b:ea
     config:     0
     state:      0
     speed: 0 Mbps now, 0 Mbps max
 LOCAL(br-tun): addr:0a:49:19:37:95:45
     config:     PORT_DOWN
     state:      LINK_DOWN
     speed: 0 Mbps now, 0 Mbps max
OFPT_GET_CONFIG_REPLY (xid=0x4): frags=normal miss_send_len=0</pre>

转发规则与计算节点类似，这里就不展开了。

### **br-int**

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ovs-vsctl show
...
    Bridge br-int
        Controller "tcp:127.0.0.1:6633"
            is_connected: true
        fail_mode: secure
        Port "tapa4f7640a-a8"
            tag: 1
            Interface "tapa4f7640a-a8"
                type: internal
        Port "qr-ae8d3c38-85"
            tag: 1
            Interface "qr-ae8d3c38-85"
                type: internal
        Port br-int
            Interface br-int
                type: internal
        Port patch-tun
            Interface patch-tun
                type: patch
                options: {peer=patch-int}
        Port int-br-prv
            Interface int-br-prv
                type: patch
                options: {peer=phy-br-prv}
        Port "qg-d68d3833-b3"
            tag: 2
            Interface "qg-d68d3833-b3"
                type: internal
...</pre>

集成网桥 br-int 主要包括以下几个接口：

<ul class="wp-block-list">
- tap 接口，连接到网络 DHCP 服务的命名空间。

- qr 接口，连接到路由服务的命名空间。

- qg 接口，连接到 router 服务的网络名字空间中，里面绑定一个路由器的外部 IP，作为 nAT 时候的地址。另外，网络中的 floating IP 也放在这个网络名字空间中。

- patch-tun 接口，连接到 br-tun 网桥。

- int-br-prv 接口，连接到 br-prv 网桥。

其中网络服务接口上会绑定内部 vlan tag，每个号对应一个网络。另外，如果 br-int 和 br-prv 只在逻辑上相连，则 qg 接口应该在 br-prv 上。

查看 br-int 的转发规则，table0 对所有包进行 NORMAL，table23 中是所有包直接丢弃。

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ovs-ofctl dump-flows br-int
NXST_FLOW reply (xid=0x4):
 cookie=0xbafffee8ff6e6051, duration=72462.496s, table=0, n_packets=72990, n_bytes=3948906, idle_age=1, hard_age=65534, priority=3,in_port=1,vlan_tci=0x0000/0x1fff actions=mod_vlan_vid:2,NORMAL
 cookie=0xbafffee8ff6e6051, duration=87152.543s, table=0, n_packets=14773, n_bytes=798318, idle_age=65534, hard_age=65534, priority=2,in_port=1 actions=drop
 cookie=0xbafffee8ff6e6051, duration=87153.303s, table=0, n_packets=733, n_bytes=76248, idle_age=376, hard_age=65534, priority=0 actions=NORMAL
 cookie=0xbafffee8ff6e6051, duration=87153.300s, table=23, n_packets=0, n_bytes=0, idle_age=65534, hard_age=65534, priority=0 actions=drop
 cookie=0xbafffee8ff6e6051, duration=87153.299s, table=24, n_packets=0, n_bytes=0, idle_age=65534, hard_age=65534, priority=0 actions=drop</pre>

### **br-prv**

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ovs-vsctl show
...
    Bridge br-prv
        Controller "tcp:127.0.0.1:6633"
            is_connected: true
        fail_mode: secure
        Port phy-br-prv
            Interface phy-br-prv
                type: patch
                options: {peer=int-br-prv}
        Port br-prv
            Interface br-prv
                type: internal
        Port external
            Interface external
                type: internal
        Port "eth1"
            Interface "eth1"
...</pre>

br-prv 主要包括以下几个接口：

<ul class="wp-block-list">
- 挂载的物理接口 eth1，网包通过这个接口发送到外部网络。

- phy-br-prv 接口，连接 br-int。

## **名字空间**

在 Linux 中，网络名字空间是一个拥有独立网络栈（网卡、路由转发表、iptables）的环境。常用来隔离网络设备和服务，只有拥有同样网络名字空间的设备，才能看到彼此。使用 ip net 命令查看已存在的名字空间：

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ip net
qrouter-f6f6ebfe-6d93-4b5f-8aea-c2c172645588
qdhcp-d60e4d79-bc10-4470-9434-297ace28ca84</pre>

### **DHCP 服务**

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ip net exec qdhcp-d60e4d79-bc10-4470-9434-297ace28ca84 ip addr
...
13: tapa4f7640a-a8: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1450 qdisc noqueue state UNKNOWN group default qlen 1
    link/ether fa:16:3e:44:48:48 brd ff:ff:ff:ff:ff:ff
    inet 10.10.10.2/24 brd 10.10.10.255 scope global tapa4f7640a-a8
       valid_lft forever preferred_lft forever
    inet6 fe80::f816:3eff:fe44:4848/64 scope link 
       valid_lft forever preferred_lft forever</pre>

可以看到，dhcp 服务的网络名字空间中只有一个网络接口 tapa4f7640a-a8，连接到 br-int 的 tapa4f7640a-a8 接口上。dhcp 服务通过 dnsmasq 进程来实现，该进程绑定到 dhcp 名字空间中的 br-int 的接口上。可以查看相关的进程。

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ps aux | grep d60e4d79-bc10-4470-9434-297ace28ca84
nobody   20089  0.0  0.0  51592   408 ?        S    Apr05   0:00 dnsmasq --no-hosts --no-resolv --strict-order --except-interface=lo --pid-file=/var/lib/neutron/dhcp/d60e4d79-bc10-4470-9434-297ace28ca84/pid --dhcp-hostsfile=/var/lib/neutron/dhcp/d60e4d79-bc10-4470-9434-297ace28ca84/host --addn-hosts=/var/lib/neutron/dhcp/d60e4d79-bc10-4470-9434-297ace28ca84/addn_hosts --dhcp-optsfile=/var/lib/neutron/dhcp/d60e4d79-bc10-4470-9434-297ace28ca84/opts --dhcp-leasefile=/var/lib/neutron/dhcp/d60e4d79-bc10-4470-9434-297ace28ca84/leases --dhcp-match=set:ipxe,175 --bind-interfaces --interface=tapa4f7640a-a8 --dhcp-range=set:tag0,10.10.10.0,static,86400s --dhcp-option-force=option:mtu,1450 --dhcp-lease-max=256 --conf-file=/etc/neutron/dnsmasq-neutron.conf --domain=openstacklocal</pre>

### **Router 服务**

Router 提供跨 subnet 的互联功能的。比如用户的内部网络中主机想要访问外部互联网的地址，就需要 router 来转发，因此，所有跟外部网络的流量都必须经过 router。目前 router 的实现是通过 iptables 进行的。

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ip net exec qrouter-f6f6ebfe-6d93-4b5f-8aea-c2c172645588 ip addr
14: qr-ae8d3c38-85: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1450 qdisc noqueue state UNKNOWN group default qlen 1
    link/ether fa:16:3e:fe:6e:a8 brd ff:ff:ff:ff:ff:ff
    inet 10.10.10.1/24 brd 10.10.10.255 scope global qr-ae8d3c38-85
       valid_lft forever preferred_lft forever
    inet6 fe80::f816:3eff:fefe:6ea8/64 scope link 
       valid_lft forever preferred_lft forever
15: qg-d68d3833-b3: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc noqueue state UNKNOWN group default qlen 1
    link/ether fa:16:3e:7d:c9:4e brd ff:ff:ff:ff:ff:ff
    inet 192.168.116.231/24 brd 192.168.116.255 scope global qg-d68d3833-b3
       valid_lft forever preferred_lft forever
    inet 192.168.116.224/32 brd 192.168.116.224 scope global qg-d68d3833-b3
       valid_lft forever preferred_lft forever
    inet 192.168.116.233/32 brd 192.168.116.233 scope global qg-d68d3833-b3
       valid_lft forever preferred_lft forever
    inet 192.168.116.226/32 brd 192.168.116.226 scope global qg-d68d3833-b3
       valid_lft forever preferred_lft forever
    inet6 fe80::f816:3eff:fe7d:c94e/64 scope link 
       valid_lft forever preferred_lft forever</pre>

该名字空间包含两个接口：

<ul class="wp-block-list">
- qr-ae8d3c38-85 接口与 br-int 上的 qr 接口相连。任何从 br-int 来的寻找 10.10.10.1（租户私有网段）的网包都会到达这个接口。

- qg-d68d3833-b3 接口与 br-int 上的 qg 接口相连。任何从外部来的网包，询问 192.168.116.231（默认的静态 NAT 外部地址）或 192.168.116.224（租户申请的 floating IP 地址），都会到达这个接口。

查看该名字空间的路由表：

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ip net exec qrouter-f6f6ebfe-6d93-4b5f-8aea-c2c172645588 ip route
default via 192.168.116.1 dev qg-d68d3833-b3 
10.10.10.0/24 dev qr-ae8d3c38-85  proto kernel  scope link  src 10.10.10.1 
192.168.116.0/24 dev qg-d68d3833-b3  proto kernel  scope link  src 192.168.116.231 </pre>

默认情况以及访问外部网络的时候，网包会从 qg-d68d3833-b3 接口发出，经过 br-int 传输到 br-prv 发布到外网。而访问租户内网的时候，会从 qr-ae8d3c38-85 接口发出，发送给 br-int。

其中 SNAT 和 DNAT 规则完成外部 floating ip（192.168.116.\*）到内部 ip（10.10.10.\*） 的映射：

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ip netns exec qrouter-f6f6ebfe-6d93-4b5f-8aea-c2c172645588 iptables -t nat -S
...
-A neutron-l3-agent-OUTPUT -d 192.168.116.233/32 -j DNAT --to-destination 10.10.10.12
-A neutron-l3-agent-OUTPUT -d 192.168.116.224/32 -j DNAT --to-destination 10.10.10.10
-A neutron-l3-agent-OUTPUT -d 192.168.116.226/32 -j DNAT --to-destination 10.10.10.13
-A neutron-l3-agent-PREROUTING -d 192.168.116.233/32 -j DNAT --to-destination 10.10.10.12
-A neutron-l3-agent-PREROUTING -d 192.168.116.224/32 -j DNAT --to-destination 10.10.10.10
-A neutron-l3-agent-PREROUTING -d 192.168.116.226/32 -j DNAT --to-destination 10.10.10.13
-A neutron-l3-agent-float-snat -s 10.10.10.12/32 -j SNAT --to-source 192.168.116.233
-A neutron-l3-agent-float-snat -s 10.10.10.10/32 -j SNAT --to-source 192.168.116.224
-A neutron-l3-agent-float-snat -s 10.10.10.13/32 -j SNAT --to-source 192.168.116.226
...</pre>

另外有一条 SNAT 规则把所有其他从 qg-d68d3833-b3 口出来的流量都映射到外部 IP 192.168.116.231。这样即使在内部虚拟机没有外部IP的情况下，也可以发起对外网的访问。

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# ip netns exec qrouter-f6f6ebfe-6d93-4b5f-8aea-c2c172645588 iptables -t nat -S
...
-A neutron-l3-agent-snat -o qg-d68d3833-b3 -j SNAT --to-source 192.168.116.231
...</pre>
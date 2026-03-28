---
title: "SR-IOV Study"
date: 2018-08-09T15:07:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [16, 27, 28]
url: http://47.84.100.47/?p=206
---


> I/O 虚拟化 I/O 虚拟化技术有三种：Device Emulation、PC…

---

## I/O 虚拟化

I/O 虚拟化技术有三种：Device Emulation、PCI Pass-through 和 SR-IOV。这三种虚拟化技术在不同程度上实现了 I/O 设备的虚拟化功能。

## **PCI Pass-through**

该方式允许将宿主机中的物理 PCI 设备直接分配给虚拟机使用。KVM 支持虚拟机以独占方式访问这个宿主机的 PCI/PCI-E 设备。通过硬件支持的 VT-d 技术将设备分给虚拟机后，在虚拟机看来，设备是物理上连接在 PCI 或者 PCIe 总线上的，客户机对该设备的 I/O 交互操作和实际的物理设备操作完全一样，几乎不需要 KVM 的参与。运行在 VT-d 平台上的 QEMU/KVM 可以分配网卡、磁盘控制器、USB控制器等设备供虚拟机直接使用。

<ul class="wp-block-list">
- 优点：在执行 I/O 操作时绕过 Hypervisor 层，直接访问物理 I/O 设备，极大地提高了性能，可以达到几乎和原生系统一样的性能。

<ul class="wp-block-list">
<li>缺点：
<ul class="wp-block-list">
- 一台服务器主板上允许添加的 PCI 和 PCIe 设备有限，大量使用 VT-d 独立分配设备给客户机，让硬件设备数量增加，增加硬件投资成本。

- 对于使用 VT-d 直接分配了设备的客户机，无法进行动态迁移。

</li>

## **SR-IOV**

### **基本原理**

VT-d 的性能非常好，但是它的物理设备只能分配给一个虚拟机使用。为了实现多个虚拟机共享一个物理设备，并且达到直接分配的目的，PCI-SIG 组织发布了 SR-IOV （Single Root I/O Virtualization） 规范，它定义了一个标准化的机制用以原生地支持实现多个客户机共享一个设备。SR-IOV 广泛应用在网卡上。

SR-IOV 使得一个单一的功能单元（比如，一个以太网端口）能看起来像多个独立的物理设备。一个带有 SR-IOV 功能的物理设备能被配置为多个功能单元。SR-IOV 使用两种功能：

<ul class="wp-block-list">
- PF（Physical Functions）：这是完整的带有 SR-IOV 能力的PCIe 设备。PF 能像普通 PCI 设备那样被发现、管理和配置。

- VF（Virtual Functions）：简单的 PCIe 功能，它只能处理 I/O。每个 VF 都是从 PF 中分离出来的。每个物理硬件都有一个 VF 数目的限制。一个 PF 能被虚拟成多个 VF 用于分配给多个虚拟机。

Hypervisor 能将一个或者多个 VF 分配给一个虚拟机。在某一时刻，一个 VF 只能被分配给一个虚拟机。

<figure class="wp-block-image size-large"><img decoding="async" width="424" height="335" src="https://maxwellii.com/wp-content/uploads/2023/11/14765271520676.png?w=424" alt="" class="wp-image-209" srcset="http://47.84.100.47/wp-content/uploads/2023/11/14765271520676.png 424w, http://47.84.100.47/wp-content/uploads/2023/11/14765271520676-300x237.png 300w" sizes="(max-width: 424px) 100vw, 424px" /></figure>

### **先决条件**

<ol class="wp-block-list">
- 需要 CPU 支持 Intel VT-x 和 VT-d 并在 BIOS 中已启用。

- 硬件设备需要支持 SR-IOV 功能。

- 需要 QEMU/KAM 的支持。

### **SR-IOV 部署**

官方配置文档请参考：[SR-IOV-Passthrough-For-Networking](https://wiki.openstack.org/wiki/SR-IOV-Passthrough-For-Networking#SR-IOV_Networking_in_OpenStack)

#### **Step1 配置 BIOS 和 Linux 内核 (Compute)**

在所有需要配置 SR-IOV 的计算节点上开启 Intel VT-d 和 SR-IOV 配置。

并且将以下参数添加到 /etc/default/grub 中的 GRUB_CMDLINE_LINUX 配置项中。

<pre class="wp-block-syntaxhighlighter-code">$ grub2-mkconfig -o /boot/grub2/grub.cfg</pre>

#### **Step2 通过 PCI SYS 接口在网卡上创建多个 VF (Compute)**

<pre class="wp-block-syntaxhighlighter-code">$ echo '0' > /sys/class/net/eth1/device/sriov_numvfs
$ echo '7' > /sys/class/net/eth1/device/sriov_numvfs</pre>

#### **Step3 设置 nova-compute 设备白名单 (Compute)**

<pre class="wp-block-syntaxhighlighter-code">$ crudini --set /etc/nova/nova.conf DEFAULT pci_passthrough_whitelist "{\"devname\": \"eth1\", \"physical_network\": \"physnet\"}"
$ service openstack-nova-compute restart</pre>

#### **Step4 配置 sriov_agent.ini 并启动 sriov-agent 服务 (compute)**

<pre class="wp-block-syntaxhighlighter-code">$ crudini --set /etc/neutron/plugins/ml2/sriov_agent.ini sriov_nic physical_device_mappings physnet:eth1
$ crudini --set /etc/neutron/plugins/ml2/sriov_agent.ini sriov_nic exclude_devices
$ crudini --set /etc/neutron/plugins/ml2/sriov_agent.ini securitygroup firewall_driver neutron.agent.firewall.NoopFirewallDriver

$ systemctl enable neutron-sriov-nic-agent.service
$ systemctl start neutron-sriov-nic-agent.service</pre>

#### **Step5 Neutron 使用 ML2 机制驱动来支持 SR-IOV，执行以下步骤配置 SR-IOV 驱动。(controller || network)**

<pre class="wp-block-syntaxhighlighter-code">$ crudini --set /etc/neutron/plugins/ml2/ml2_conf.ini ml2 tenant_network_types vlan,vxlan
$ crudini --set /etc/neutron/plugins/ml2/ml2_conf.ini ml2 mechanism_drivers openvswitch,sriovnicswitch
$ crudini --set /etc/neutron/plugins/ml2/ml2_conf_sriov.ini ml2_sriov supported_pci_vendor_devs 8086:1520
$ sed -i 's/plugin.ini/& --config-file \/etc\/neutron\/plugins\/ml2\/ml2_conf_sriov.ini/' /usr/lib/systemd/system/neutron-server.service

systemctl daemon-reload
service neutron-server restart</pre>

支持的 vendor_id 可以通过以下命令在计算节点上查询。

<pre class="wp-block-syntaxhighlighter-code">$ lspci -nn | grep "Ethernet Controller Virtual Function"</pre>

#### **Step6 配置 FilterScheduler (controller)**

<pre class="wp-block-syntaxhighlighter-code">$ crudini --set /etc/nova/nova.conf DEFAULT scheduler_available_filters nova.scheduler.filters.all_filters
$ crudini --set /etc/nova/nova.conf DEFAULT scheduler_default_filters RetryFilter,AvailabilityZoneFilter,RamFilter,ComputeFilter,ComputeCapabilitiesFilter,ImagePropertiesFilter,ServerGroupAntiAffinityFilter,ServerGroupAffinityFilter,PciPassthroughFilter

$ service openstack-nova-scheduler restart</pre>

#### **Step7 测试验证**

<pre class="wp-block-syntaxhighlighter-code">$ wget http://artifacts.opnfv.org/yardstick/third-party/yardstick-loopback-v1_1.img
$ source /opt/admin-openrc.sh
$ glance image-create \
    --name "yardstick" \
    --file yardstick-loopback-v1_1.img \
    --disk-format qcow2 \
    --container-format bare

$ openstack aggregate create --zone=sr-iov sr-iov
$ openstack aggregate add host sr-iov host4
$ openstack aggregate add host sr-iov host5

$ nova secgroup-add-rule default icmp -1 -1 0.0.0.0/0
$ nova secgroup-add-rule default tcp 22 22 0.0.0.0/0

$ neutron net-create ext-net \
    --provider:network_type vlan \
    --provider:segmentation_id 3603 \
    --provider:physical_network physnet \
    --router:external "True"

$ neutron subnet-create \
    --name ext-subnet \
    --gateway 192.168.36.1 \
    --allocation-pool \
    start=192.168.36.223,end=192.168.36.253 \
    ext-net 192.168.36.0/24

$ neutron port-create ext-net --name sr-iov --binding:vnic-type direct

$ openstack server create \
    --flavor m1.large \
    --image yardstick \
    --security-group default \
    --nic port-id=$(neutron port-list | grep sr-iov | awk '{print $2}') \
    --availability-zone sr-iov demo1</pre>

## **架构对比**

<figure class="wp-block-image size-large"><img decoding="async" width="555" height="351" src="https://maxwellii.com/wp-content/uploads/2023/11/041757434104956.jpg?w=555" alt="" class="wp-image-214" srcset="http://47.84.100.47/wp-content/uploads/2023/11/041757434104956.jpg 555w, http://47.84.100.47/wp-content/uploads/2023/11/041757434104956-300x190.jpg 300w" sizes="(max-width: 555px) 100vw, 555px" /></figure>

## **参考资料**

[SR-IOV Configuration Guide](https://www.intel.com/content/www/us/en/embedded/products/networking/xl710-sr-iov-config-guide-gbe-linux-brief.html)

[SR-IOV-Passthrough-For-Networking](https://wiki.openstack.org/wiki/SR-IOV-Passthrough-For-Networking#SR-IOV_Networking_in_OpenStack)

[Redhat OpenStack SR-IOV Configure](https://access.redhat.com/documentation/zh-cn/red_hat_enterprise_linux_openstack_platform/7/html/networking_guide/sec-sr-iov)

[SDN Fundamentails for NFV, Openstack and Containers](https://www.slideshare.net/nyechiel/sdn-fundamentals-for-nfv-open-stack-and-containers-red-hat-summit-20161)

[KVM Introduction: SR-IOV](http://www.cnblogs.com/sammyliu/p/4548194.html)

[PCI Passthrough of host network devices](https://wiki.libvirt.org/page/Networking#PCI_Passthrough_of_host_network_devices)

[OpenStack Networking](https://docs.openstack.org/mitaka/networking-guide/intro-os-networking.html)

[Attaching physical PCI devices to guests](https://docs.openstack.org/nova/pike/admin/pci-passthrough.html)
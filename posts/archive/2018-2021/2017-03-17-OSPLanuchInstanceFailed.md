---
title: "OSP Lanuch Instance Failed"
date: 2017-03-17T10:21:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [10, 11, 21, 23]
url: http://47.84.100.47/?p=133
---


> 问题发现 由于西安 NFV 预集成开发部项目 IaaS 需要用到红帽的 OSP8…

---

## **问题发现**

由于西安 NFV 预集成开发部项目 IaaS 需要用到红帽的 OSP8，所以要对 Compass4NFV 进行版本适配，以支持 OSP8 的集成部署。从 OpenLab 拷贝了 OSP8 的源，制作了 OSP8 的 ppa。以 OSP9 的代码作为修改基础，进行了一些简单的调整，不带 Ceph 的 OSP8 居然部署起来了，过程无比顺利，以至于一度怀疑自己装的是 OSP9。检查了 ppa 里面 nova 等包的版本号，确定版本无误。但是部署的 OSP8 无法拉起事例。

通过自己写的 ping_test.sh 脚本来拉事例，实例启动后状态直接 ERROR。

<pre class="wp-block-syntaxhighlighter-code">[root@host1 ~]# nova list
+--------------------------------------+-------+--------+------------+-------------+----------+
| ID                                   | Name  | Status | Task State | Power State | Networks |
+--------------------------------------+-------+--------+------------+-------------+----------+
| 905b005c-105c-4766-be5f-2733fe2bb992 | ping1 | ERROR  | -          | NOSTATE     |          |
+--------------------------------------+-------+--------+------------+-------------+----------+</pre>

## **问题分析**

在计算节点的 /var/log/nova/nova-compute.log 文件内截取到以下 ERROR log：

<pre class="wp-block-syntaxhighlighter-code">2017-03-19 23:59:51.598 17827 DEBUG nova.compute.utils [req-44bd1d25-53e9-4005-befc-dc3fe046fc3a 206b7b2a6c9e4bfd976d9d27e1822cd5 fd070ca7d2f0400db83b3cd72e8f9297 - - -] [instance: 905b005c-105c-4766-be5f-2733fe2bb992] Build of instance 905b005c-105c-4766-be5f-2733fe2bb992 aborted: Could not clean up failed build, not rescheduling notify_about_instance_usage /usr/lib/python2.7/site-packages/nova/compute/utils.py:284
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [req-44bd1d25-53e9-4005-befc-dc3fe046fc3a 206b7b2a6c9e4bfd976d9d27e1822cd5 fd070ca7d2f0400db83b3cd72e8f9297 - - -] [instance: 905b005c-105c-4766-be5f-2733fe2bb992] Build of instance 905b005c-105c-4766-be5f-2733fe2bb992 aborted: Could not clean up failed build, not rescheduling
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992] Traceback (most recent call last):
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]   File "/usr/lib/python2.7/site-packages/nova/compute/manager.py", line 1905, in _do_build_and_run_instance
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]     filter_properties)
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]   File "/usr/lib/python2.7/site-packages/nova/compute/manager.py", line 2049, in _build_and_run_instance
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]     'create.error', fault=e)
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]   File "/usr/lib/python2.7/site-packages/oslo_utils/excutils.py", line 204, in __exit__
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]     six.reraise(self.type_, self.value, self.tb)
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]   File "/usr/lib/python2.7/site-packages/nova/compute/manager.py", line 2033, in _build_and_run_instance
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]     block_device_info=block_device_info)
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]   File "/usr/lib64/python2.7/contextlib.py", line 35, in __exit__
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]     self.gen.throw(type, value, traceback)
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]   File "/usr/lib/python2.7/site-packages/nova/compute/manager.py", line 2204, in _build_resources
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]     instance_uuid=instance.uuid, reason=msg)
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992] BuildAbortException: Build of instance 905b005c-105c-4766-be5f-2733fe2bb992 aborted: Could not clean up failed build, not rescheduling
2017-03-19 23:59:51.604 17827 ERROR nova.compute.manager [instance: 905b005c-105c-4766-be5f-2733fe2bb992]</pre>

在计算节点上检查实例的 dumpxml文件：

<pre class="wp-block-syntaxhighlighter-code">...
  <cpu mode='host-model'>
    <model fallback='allow'/>
    <topology sockets='1' cores='1' threads='1'/>
  </cpu>
...</pre>

发现 cpu mode 值为 ‘host-model’。在官方配置文件中找到对于 cpu_mode 配置项的说明：*Set to “host-model” to clone the host CPU feature flags; to “host-passthrough” to use the host CPU model exactly; to “custom” to use a named CPU model; to “none” to not set any CPU model. If virt_type=”kvm|qemu”, it will default to “host-model”, otherwise it will default to “none”*。检查 nova-compute.conf，发现 virt_type=kvm，故 cpu_mode 的默认值为 host-model。

参阅官方文档：[LibvirtXMLCPUModel](https://wiki.openstack.org/wiki/LibvirtXMLCPUModel)

<ul class="wp-block-list">
- cpu_mode=host-model 时，会根据物理 CPU 的特性，选择一个最靠近的标准 CPU 型号进行虚拟化模拟。这种方式在迁移虚拟机时也具有较好的兼容性。

- cpu_mode=host-passthrough 时，KVM 不允许修改 cpu model，而要严格的去匹配每一个 cpu flags。这样做会提供更好的性能，缺点就是迁移的时候代价太大，只允许迁移到相同 cpu 的主机上。

- cpu_mode=custom 时，cpu_model 配置项才会生效，可以通过这个配置项来明确配置一个受支持的命名模型。

- cpu_mode=none 时，对除了 KVM & QEMU 的所有 libvirt－driven 的 hypervisors 均为默认值，让 hypervisor 来选择默认的 model。

## **解决方案**

在计算节点 nova-compute.conf 配置文件中加入 cpu_mode=none。

## **问题反思**

这个问题在调试 Centos Newton 的时候已经出现过，那时候没有做好记录，所以复现时仍花了不少时间来定位。
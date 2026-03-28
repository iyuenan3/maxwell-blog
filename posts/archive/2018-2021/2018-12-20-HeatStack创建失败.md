---
title: "Heat Stack 创建失败"
date: 2018-12-20T15:58:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [11, 13, 29]
url: http://47.84.100.47/?p=234
---


> 问题记录 环境：OSP10 1Controller + 4Compute SR-…

---

## **问题记录**

环境：OSP10 1Controller + 4Compute SR-IOV

现象：创建 Stack 时，出现 504 Gateway 错误。

<pre class="wp-block-syntaxhighlighter-code">[sqg04@controller-0 ~]$ heat stack-show vnfcs_knife_stack 
...
| stack_status          | CREATE_FAILED                                            |
| stack_status_reason   | Resource CREATE failed: ResourceInError:                 |
|                       | resources.oam_node_in_redundant.resources.server: Went   |
|                       | to status ERROR due to "Message: Exceeded maximum        |
|                       | number of retries. Exceeded max scheduling attempts 3    |
|                       | for instance 950e7b96-b071-4b2c-8007-8f5a8174bd10. Last  |
|                       | exception: 504 Gateway Time-out: The server didn't       |
|                       | respond in time. (HTTP N/A), Code: 500"                  |
...</pre>

只含有网络的 Stack 能够创建成功，怀疑是存储的问题。

## **解决方案**

### **Step1. 按 OpenStack 官方文档创建 Demo Stack**

官方文档参考：[Create Stack](https://docs.openstack.org/project-install-guide/orchestration/ocata/launch-instance.html)

<pre class="wp-block-syntaxhighlighter-code">[heat-admin@controller-0 ~]$ source overcloudrc
[heat-admin@controller-0 ~]$ openstack image create "cirros" --file cirros-0.3.4-x86_64-disk.img --disk-format qcow2 --container-format bare --public
''
[heat-admin@controller-0 ~]$ openstack image list
+--------------------------------------+-----------------------+--------+
| ID                                   | Name                  | Status |
+--------------------------------------+-----------------------+--------+
| d8508e06-e2a4-4f7c-9812-6737063aa9c8 | robot_test_image      | active |
| 0f2c805b-eda6-4f14-b57e-614d7c58ea63 | 174_upgrade_main      | active |
| 12183805-f185-471d-94a1-7cefdacab617 | 174_Image_for_Upgrade | active |
| 38ed2488-d76c-4a5e-8045-b2c058a7daa9 | knife_ding            | active |
| acf243cb-0a66-4cbd-80ee-e709961a7554 | 5gbts_4.3113.652      | active |
+--------------------------------------+-----------------------+--------+</pre>

发现创建 Cirros Image 失败。

### **Step2. 尝试重启所有 Swift 服务**

<pre class="wp-block-syntaxhighlighter-code">[heat-admin@controller-0 ~]$ for i in $(systemctl | grep swift | awk '{print $1}'); do sudo systemctl restart $i; done</pre>

### **Step3. 再次上传 Image 成功**

<pre class="wp-block-syntaxhighlighter-code">[heat-admin@controller-0 ~]$ openstack image create "cirros" --file cirros-0.3.4-x86_64-disk.img --disk-format qcow2 --container-format bare --public --debug
+------------------+------------------------------------------------------------------------------+
| Field            | Value                                                                        |
+------------------+------------------------------------------------------------------------------+
| checksum         | ee1eca47dc88f4879d8a229cc70a07c6                                             |
| container_format | bare                                                                         |
| created_at       | 2018-12-20T02:19:51Z                                                         |
| disk_format      | qcow2                                                                        |
| file             | /v2/images/b4543214-06a4-44fc-bd63-b94c565253a1/file                         |
| id               | b4543214-06a4-44fc-bd63-b94c565253a1                                         |
| min_disk         | 0                                                                            |
| min_ram          | 0                                                                            |
| name             | cirros                                                                       |
| owner            | e51c3027da254fa191aa42f0c46cdccc                                             |
| properties       | direct_url='swift+config://ref1/glance/b4543214-06a4-44fc-bd63-b94c565253a1' |
| protected        | False                                                                        |
| schema           | /v2/schemas/image                                                            |
| size             | 13287936                                                                     |
| status           | active                                                                       |
| tags             |                                                                              |
| updated_at       | 2018-12-20T02:19:53Z                                                         |
| virtual_size     | None                                                                         |
| visibility       | public                                                                       |
+------------------+------------------------------------------------------------------------------+</pre>

### **Step4. 创建 Stack 成功**

<pre class="wp-block-syntaxhighlighter-code">[heat-admin@controller-0 ~]$ openstack stack list
+--------------------------------------+---------------------------------------+-----------------+----------------------+--------------+
| ID                                   | Stack Name                            | Stack Status    | Creation Time        | Updated Time |
+--------------------------------------+---------------------------------------+-----------------+----------------------+--------------+
| 11fe9f53-6e09-49a6-ae99-95a30956518c | CBAM-e6b45c9d95d04635b4bd54de19301e30 | CREATE_COMPLETE | 2018-12-20T02:23:20Z | None         |
+--------------------------------------+---------------------------------------+-----------------+----------------------+--------------+
[heat-admin@controller-0 ~]$ openstack stack show 11fe9f53-6e09-49a6-ae99-95a30956518c
+-----------------------+-----------------------------------------+
| Field                 | Value                                   |
+-----------------------+-----------------------------------------+
| id                    | 11fe9f53-6e09-49a6-ae99-95a30956518c    |
| stack_name            | CBAM-e6b45c9d95d04635b4bd54de19301e30   |
| description           | Template for create all VNFCs           |
| creation_time         | 2018-12-20T02:23:20Z                    |
| updated_time          | None                                    |
| stack_status          | CREATE_COMPLETE                         |
| stack_status_reason   | Stack CREATE completed successfully     |
...</pre>

## **分析总结**

该问题其实已经出现很多次了，从最初 Donna 发现 Image 无法上传删除到后来的 Instance 无法创建，仔细一想都是与存储相关的。原本计划按照官方文档使用 Heat Template 来创建一个 Instance，结果上传 Image 就出现了错误。另外在定位过程中，彩虹说只包含网络的 Stack 能够创建成功，那就没跑了。用一条命令 `for i in $(systemctl | grep swift | awk '{print $1}'); do sudo systemctl restart $i; done` 重启了所有 Swift 服务（该环境使用 Swift 来存储 Image），问题果然迎刃而解。

但是仔细想想，重启服务这个方案，治标不治本。一套健康的环境，理论上是不会出现这些问题的。想到的第一个方案就是用其他方案来代替 Swift。但是和健哥讨论之后，这是客户对环境的需求，无法修改。那就要从 Swift 本身来着手。在重启 Swift 服务后查看状态，发现部分服务有如下错误：

<pre class="wp-block-syntaxhighlighter-code">Dec 20 10:18:40 controller-0.localdomain systemd[1]: Started OpenStack Object Storage (swift) -  Object Updater.
Dec 20 10:18:40 controller-0.localdomain systemd[1]: Starting OpenStack Object Storage (swift) - Object Updater...
Dec 20 10:18:40 controller-0.localdomain liberasurecode[771222]: liberasurecode_instance_create: dynamic linking error libJerasure.so.2: cannot open shared object file: No such file or directory
Dec 20 10:18:40 controller-0.localdomain liberasurecode[771222]: liberasurecode_instance_create: dynamic linking error libJerasure.so.2: cannot open shared object file: No such file or directory
Dec 20 10:18:40 controller-0.localdomain liberasurecode[771222]: liberasurecode_instance_create: dynamic linking error libisal.so.2: cannot open shared object file: No such file or directory
Dec 20 10:18:40 controller-0.localdomain liberasurecode[771222]: liberasurecode_instance_create: dynamic linking error libshss.so.1: cannot open shared object file: No such file or directory</pre>

错误显示无法找到共享文件，但是 Swift 功能正常。猜测这可能与 Swift 部署在 Controller 节点上，并且只有一个 Controller 节点有关。如果有多个 Controller 节点，或者 Swift 部署在多个 Compute 节点上，就会有共享文件来进行分布式存储。

另外，在 /var/log/swift/swift.log 文件内也没有找到任何 ERROR，也没有其他思路，问题暂时中断。
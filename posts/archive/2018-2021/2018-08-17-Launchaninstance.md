---
title: "Launch an instance"
date: 2018-08-17T15:16:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [15, 20, 21]
url: http://47.84.100.47/?p=217
---


> 创建实例步骤 参考链接：https://ilearnstack.com/2013…

---

## **创建实例步骤**

<figure class="wp-block-image size-large"><img fetchpriority="high" decoding="async" width="1112" height="724" src="https://maxwellii.com/wp-content/uploads/2023/11/201806161742.png?w=1024" alt="" class="wp-image-218" srcset="http://47.84.100.47/wp-content/uploads/2023/11/201806161742.png 1112w, http://47.84.100.47/wp-content/uploads/2023/11/201806161742-300x195.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/201806161742-1024x667.png 1024w, http://47.84.100.47/wp-content/uploads/2023/11/201806161742-768x500.png 768w" sizes="(max-width: 1112px) 100vw, 1112px" /></figure>

<ol class="wp-block-list">
- dashboard 或者 CLI 获取用户的登录信息，调用 keystone 的 REST API 进行用户身份验证。

- keystone 对用户登录信息进行校验，然后产生验证 token 并发回。它会被用于后续 REST 调用请求。

- dashboard 或者 CLI 将 REST API 请求中的 ‘launch instance’ 或 ‘nova-boot’ 部分转换成创建实例请求，并发送给 nova-api。

- nova-api 接到请求，向 keystone 发送 auth-token 校验和权限认证请求。

- keystone 校验 token，并将 auth headers 发回，它包括了 roles 和 permissions。

- nova-api 和 nova-database 进行交互。

- nova-database 为新实例创建一个数据库条目。

- nova-api 向 nova-scheduler 发送 rpc.call 请求，期望它能通过附带的 host ID 获取到实例的数据库条目。

- nova-scheduler 从 queue 中获取到请求。

- nova-scheduler 和 nova-database 交互，获取集群中计算节点的信息和状态。

- nova-scheuler 通过过滤（filtering）和称重（weighting）找到一个合适的计算节点（host）。

- nova-scheduler 向目标 host 上的 nova-compute 发送 rpc.cast 请求去启动实例。

- 目标 host 上的 nova-compute 从 queue 中获取到请求。

- nova-compute 向 nova-condutor 发送 rpc.call 请求去获取待创建实例的信息，例如 host ID、flavor、CPU、RAM、Disk 等。

- nova-conductor 从 queue 中获取到请求。

- nova-conductor 和 nova-database 交互。

- nova-database 向 nova-conductor 返回实例的信息。

- 实例信息通过 queue，从 nova-conductor 发送给 nova-compute。

- nova-compute 通过 REST API 将 auth-token 传入 glance-api，根据 image ID 获取镜像 URI，并从镜像存储中下载镜像。

- glance-api 向 keystone 校验 auth-token。

- nova-compute 获取 image metadata。

- nova-compute 通过 REST API 将 auth-token 传入 Network API 来配置网络，例如实例的 IP 地址。

- neutron-server 通过 keystone 校验 auth-token。

- nova-compute 获得网络信息。

- nova-compute 通过 REST API 将 auth-token 传入 Volume API，将 volume 挂载到实例。

- cinder-api 通过 keystone 校验 auth-token。

- nova-compute 获得 block storage 信息。

- nova-compute 为 hypervisor driver 产生数据，并调用 hypersior 执行请求（通过 libvirt 或者 api）。

参考链接：https://ilearnstack.com/2013/04/26/request-flow-for-provisioning-instance-in-openstack/
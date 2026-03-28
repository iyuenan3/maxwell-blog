---
title: "RedHat OpenStack Platform  部署指南"
date: 2018-12-18T15:55:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [19, 23, 27]
url: http://47.84.100.47/?p=231
---


<div class="wp-block-jetpack-markdown">官方文档请参考：[RedHat OpenStack Platform 13 director 的安装和使用](https://access.redhat.com/documentation/zh-cn/red_hat_openstack_platform/13/pdf/director_installation_and_usage/Red_Hat_OpenStack_Platform-13-Director_Installation_and_Usage-zh-CN.pdf)

## 1. 安装 UNDERCLOUD

### 1.1. UNDERCLOUD 环境准备

**1.1.1. 创建虚拟机**

使用 VirtualBox 创建一台虚拟机，配置需求如下：

- CPU：16 Cores
- Memory：32768MB
- Storage: 256GB
<li>Network：

- enp0s3 bridge to eth0
- enp0s8 bridge to eth1

</li>

使用最新的 RedHat 7.6 镜像安装操作系统，并启动虚拟机。

**1.1.2. 配置网络**

使用以下命令配置虚拟机 ip：

```
[root@localhost ~]# ip addr add 10.57.199.224/24 dev enp0s3
[root@localhost ~]# ip route add default via 10.57.199.1

```

为了防止虚拟机重启后 ip 消失，可以将配置信息写入 `/etc/sysconfig/network-scripts/ifcfg-enp0s3` 文件：

```
TYPE=Ethernet
PROXY_METHOD=none
BROWSER_ONLY=no
BOOTPROTO=static
DEFROUTE=yes
IPV4_FAILURE_FATAL=no
IPV6INIT=yes
IPV6_AUTOCONF=yes
IPV6_DEFROUTE=yes
IPV6_FAILURE_FATAL=no
IPV6_ADDR_GEN_MODE=stable-privacy
NAME=enp0s3
UUID=19a025b8-eab1-482c-a079-e7e0ba279395
DEVICE=enp0s3
ONBOOT=yes
HWADDR=08:00:27:7d:c1:93
IPADDR=10.57.199.242
NETMASK=255.255.255.0
GATEWAY=10.57.199.1
DNS=10.56.126.31

```

然后重启网卡：

```
[root@localhost ~]# systemctl restart network.service

```

配置 DNS，在 `/etc/resolve.conf` 文件中加入以下内容：

```
nameserver 10.56.126.31

```

**1.1.3. 允许 ROOT 用户远程接入**

修改 `/etc/ssh/sshd_config` 文件，将 `# PermitRootLogin yes` 的注释取消，保存退出。

### 1.2. 创建 STACK 用户

在安装 director 的过程中，需要一个非 root 用户来执行命令。按照以下过程，创建一个名为 stack 的
用户并设置密码。

**1.2.1. 创建 stack 用户并设置密码**

```
[root@localhost ~]# useradd stack
[root@localhost ~]# passwd stack

```

**1.2.2. 进行以下操作，以使用户在使用 sudo 时无需输入密码**

```
[root@localhost ~]# echo "stack ALL=(root) NOPASSWD:ALL" | tee -a /etc/sudoers.d/stack
[root@localhost ~]# chmod 0440 /etc/sudoers.d/stack

```

**1.2.3. 切换到新的 stack 用户**

```
[root@localhost ~]# su - stack

```

后续操作均使用 stack 用户完成。

### 1.3. 注册和更新 UNDERCLOUD

在安装 director 之前，请先执行以下步骤：

- 使用 Red Hat Subscription Manager 注册 undercloud
- 订阅并启用相关的软件仓库
- 更新 Red Hat Enterprise Linux 软件包

**1.3.1. 配置 Proxy**

修改 `/etc/rhsm/rhsm.conf` 文件：

```
# an http proxy server to use
proxy_hostname = 10.144.1.10    

# port for http proxy server
proxy_port = 8080

```

在 `/etc/yum.conf` 文件中加入以下内容：

```
proxy=http://10.144.1.10:8080/
proxy=https://10.144.1.10:8080/

```

**1.3.2. 注册系统**

在 Content Delivery Network 中注册您的系统，在出现提示时输入您的用户门户网站的用户名和
密码：

```
[stack@localhost ~]$ sudo subscription-manager register

```

**1.3.3. 查找 RedHat OpenStack Platform director 的权利池 ID**

```
[stack@localhost ~]$ sudo subscription-manager list --available --all --matches="Red Hat OpenStack"
+-------------------------------------------+
    Available Subscriptions
+-------------------------------------------+
Subscription Name:   Red Hat OpenStack Platform, Self-Support (4 Sockets, NFR, Partner Only)
Provides:            dotNET on RHEL Beta (for RHEL Server)
                     Red Hat Enterprise Linux FDIO Early Access (RHEL 7 Server)
                     Red Hat Ansible Engine
                     Red Hat Ceph Storage
                     Red Hat OpenStack Certification Test Suite
                     Red Hat OpenStack Beta
                     Red Hat CloudForms
                     Red Hat OpenStack Beta for IBM Power LE
                     Red Hat OpenStack
                     Red Hat Ceph Storage MON
SKU:                 S****4
Contract:            11*****98
Pool ID:             8a85f9******************015f9
Provides Management: No
Available:           Unlimited
Suggested:           1
Service Level:       Self-Support
Service Type:        L1-L3
Subscription Type:   Standard
Starts:              12/07/2018
Ends:                12/06/2019
System Type:         Virtual

```

**1.3.4. 查找 Pool ID 的值并附加 Red Hat OpenStack Platform 13 的权利**

```
[stack@localhost ~]$ sudo subscription-manager attach --pool=8a85f9******************015f9
Successfully attached a subscription for: Red Hat OpenStack Platform, Self-Support (4 Sockets, NFR, Partner Only)
1 local certificate has been deleted.

```

**1.3.5. 禁用所有默认的仓库，启用 RedHat Enterprise Linux 仓库**

```
[stack@localhost ~]$ sudo subscription-manager repos --disable=*
[stack@localhost ~]$ sudo subscription-manager repos \
     --enable=rhel-7-server-rpms \
     --enable=rhel-7-server-extras-rpms \
     --enable=rhel-7-server-rh-common-rpms \
     --enable=rhel-ha-for-rhel-7-server-rpms \
     --enable=rhel-7-server-openstack-13-rpms

```

**1.3.6. 更新系统上的软件**

```
[stack@localhost ~]$ sudo yum update -y
[stack@localhost ~]$ sudo reboot

```

### 1.4. 安装 DIRECTOR 软件包

**1.4.1. 安装用于配置 director 的命令行工具**

```
[stack@localhost ~]$ sudo yum install -y python-tripleoclient

```

**1.4.2. 如果要创建包含 Ceph Storage 节点的 overcloud，需要额外安装 ceph-ansible 软件包**

```
[stack@localhost ~]$ sudo yum install -y ceph-ansible

```

### 1.5. 配置 DIRECTOR

在安装 director 时，需要使用特定的设置来决定您的网络配置。这些设置存储在 stack 用户的主目录内
的一个模板中，即 undercloud.conf。以下操作过程展示了如何基于这个默认模板来进行配置。

**1.5.1. 复制红帽的模板到 stack 用户的 home 目录下**

```
[stack@localhost ~]$ cp /usr/share/instack-undercloud/undercloud.conf.sample ~/undercloud.conf

```

**1.5.2. 编辑 undercloud.conf 文件**

主要进行如下修改：

```
[DEFAULT]
undercloud_hostname = undercloud.localdomain
local_ip = 192.168.24.1/24
undercloud_public_host = 192.168.24.2
undercloud_admin_host = 192.168.24.3
local_interface = enp0s8
local_mtu = 1500
masquerade_network = 192.168.24.0/24
inspection_interface = br-ctlplane
[ctlplane-subnet]
cidr = 192.168.24.0/24
dhcp_start = 192.168.24.5
dhcp_end = 192.168.24.24
inspection_iprange = 192.168.24.30,192.168.24.50
gateway = 192.168.24.1

```

### 1.6. 部署 UNDERCLOUD

运行以下命令，在 undercloud 上安装 director

```
[stack@localhost ~]$ openstack undercloud install

```

这会启动 director 的配置脚本。director 会安装额外的软件包，并根据 undercloud.conf 来进行服务配置。完成后，会生成两个文件：

- undercloud-passwords.conf – director 服务的所有密码列表。
- stackrc – 用来访问 director 命令行工具的一组初始变量。

### 1.7. 为 OVERCLOUD 节点获取镜像

director 需要以下几个磁盘镜像来部署 overcloud 节点：

- 一个内省内核和 ramdisk：用于通过 PXE 引导进行裸机系统内省。
- 一个实施内核和 ramdisk：用于系统部署和实施。
- overcloud 内核、ramdisk 和完整镜像：写到节点硬盘中的基本 overcloud 系统。

**1.7.1. 查找 stackrc 文件，以启用 director 的命令行工具**

```
[stack@undercloud ~]$ source ~/stackrc

```

**1.6.2. 安装 rhosp-director-images 和 rhosp-director-images-ipa 软件包**

```
(undercloud) [stack@undercloud ~]$ sudo yum install -y rhosp-director-images rhosp-director-images-ipa

```

**1.6.3. 把压缩文件展开到 stack 用户 `/home/stack/images` 目录下**

```
(undercloud) [stack@undercloud ~]$ mkdir images
(undercloud) [stack@undercloud ~]$ cd images/
(undercloud) [stack@undercloud images]$ tar -xvf /usr/share/rhosp-director-images/overcloud-full-13.0-20181107.1.el7ost.x86_64.tar
(undercloud) [stack@undercloud images]$ tar -xvf /usr/share/rhosp-director-images/ironic-python-agent-latest-13.0.tar

```

**1.6.4. 将镜像导入到 director**

```
(undercloud) [stack@undercloud ~]$ openstack overcloud image upload --image-path ~/images/

```

**1.6.5. 检查镜像是否上传成功**

```
(undercloud) [stack@undercloud ~]$ openstack image list
+--------------------------------------+------------------------+--------+
| ID                                   | Name                   | Status |
+--------------------------------------+------------------------+--------+
| 23a31d97-b28e-429b-bc41-d67ca27073b1 | bm-deploy-kernel       | active |
| 6affc8c9-b658-48c9-9324-b3080d92e9de | bm-deploy-ramdisk      | active |
| 29070cb6-77b1-40ce-aff2-2343dba0302d | overcloud-full         | active |
| b7d9950a-dd6c-4ff2-9e61-9fb276c95d5d | overcloud-full-initrd  | active |
| 0bfe8abb-a861-4bcb-9456-9023c2b0cfed | overcloud-full-vmlinuz | active |
+--------------------------------------+------------------------+--------+

```

### 1.8. 为 CONTROL PLANE 设置 nameserver

为 ctlplane-subnet 子网设置 nameserver

```
(undercloud) [stack@undercloud ~]$ openstack subnet set ctlplane-subnet --dns-nameserver 10.56.126.31

```

## 2. 配置容器镜像源

### 2.1. REGISTRY

RedHat OpenStack Platform 支持以下 register 类型：

<li>
远程注册表
overcloud 会直接从 registry.access.redhat.com 中提取容器镜像。这是最简单的一种初始配置生成方法。但是，每个 overcloud 节点都会直接从 Red Hat Container Catalog 中提取所有的镜像，这可能会导致网络拥塞并影响部署速度。此外，所有 overcloud 节点都需要通过互联网来访问 RedHat Container Catalog。

</li>
<li>
本地注册表
可以在 undercloud 上创建本地 register，并从 registry.access.redhat.com 同步镜像；overcloud 则会从 undercloud 提取容器镜像。此方法允许您在内部存储 register，这样可以加快部署速度并缓解网络拥塞。但是，undercloud 只能用作基础register，而且只能为容器镜像提供有限的生命周期管理。

</li>
<li>
Satellite 服务器
通过 Red Hat Satellite 6 服务器，可管理容器镜像的整个应用生命周期并发布镜像。overcloud 会从 Satellite 服务器提取镜像。此方法提供了一个可用于存储、管理和部署 Red Hat OpenStack Platform 容器的企业级解决方案。

</li>

本文采用本地注册表方式。

### 2.2. 使用 UNDERCLOUD 作为本地 REGISTRY

**2.2.1. 查找本地 undercloud registry 的地址。**

```
(undercloud) [stack@undercloud ~]$ ifconfig br-ctlplane
br-ctlplane: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
        inet 192.168.24.1  netmask 255.255.255.0  broadcast 192.168.24.255
        inet6 fe80::a00:27ff:fe64:1634  prefixlen 64  scopeid 0x20<link>
        ether 08:00:27:64:16:34  txqueuelen 1000  (Ethernet)
        RX packets 1611  bytes 147564 (144.1 KiB)
        RX errors 0  dropped 0  overruns 0  frame 0
        TX packets 14  bytes 908 (908.0 B)
        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0

```

**2.2.2. 设置 proxy**

设置 docker proxy：

```
(undercloud) [stack@undercloud ~]$ sudo cat >> /etc/systemd/system/docker.service.d/99-unset-mountflags.conf << EOF
Environment="HTTP_PROXY=http://10.144.1.10:8080"
Environment="HTTPS_PROXY=http://10.144.1.10:8080"
Environment="NO_PROXY=localhost,127.0.0.1,192.168.24.1"
EOF
systemctl daemon-reload
systemctl restart docker

```

设置全局 proxy：

```
(undercloud) [stack@undercloud ~]$ export http_proxy=http://10.144.1.10:8080/
(undercloud) [stack@undercloud ~]$ export no_proxy="localhost,127.0.0.1,192.168.24.1"

```

- 注意：必须把 192.168.24.1 加入到 no_proxy 中，否则无法鉴权。

**2.2.3. 创建模板文件**

```
(undercloud) [stack@undercloud ~]$ mkdir templates
(undercloud) [stack@undercloud ~]$ touch templates/overcloud_images.yaml
(undercloud) [stack@undercloud ~]$ touch local_registry_images.yaml

```

**2.2.3. 将镜像上传到本地 registry，并引用这些镜像**

```
(undercloud) [stack@undercloud ~]$ openstack overcloud container image prepare \
    --namespace=registry.access.redhat.com/rhosp13 \
    --push-destination=192.168.24.1:8787 \
    --prefix=openstack- \
    --tag-from-label {version}-{release} \
    --output-env-file=/home/stack/templates/overcloud_images.yaml \
    --output-images-file /home/stack/local_registry_images.yaml

```

- local_registry_images.yaml，包含来自远程来源的容器镜像信息。
- overcloud_images.yaml，包含镜像文件在 undercloud 上所处的最终位置。

**2.2.4. 将 registry.access.redhat.com 中的容器镜像提取到 undercloud**

```
(undercloud) [stack@undercloud ~]$ sudo openstack overcloud container image upload \
     --config-file /home/stack/local_registry_images.yaml \
     --verbose

```

- 提取所需镜像需要花费一定时间，具体取决于网络速度及 undercloud 磁盘情况。

## 3. 配置 OVERCLOUD

### 3.1. 为 OVERCLOUD 注册节点

director 需要一个节点定义模板 instackenv.json，它包括了节点的详细硬件信息和电源管理信息。例如：

```
{
	"nodes": [
	{
			"mac": [
				"bb:bb:bb:bb:bb:bb"
			],
			"name": "node01",
			"cpu": "4",
			"memory": "6144",
			"disk": "40",
			"arch": "x86_64",
			"pm_type": "pxe_ipmitool",
			"pm_user": "admin",
			"pm_password": "p@55w0rd!",
			"pm_addr": "192.168.24.205"
		},
		{
			"mac": [
				"cc:cc:cc:cc:cc:cc"
			],
			"name": "node02",
			"cpu": "4",
			"memory": "6144",
			"disk": "40",
			"arch": "x86_64",
			"pm_type": "pxe_ipmitool",
			"pm_user": "admin",
			"pm_password": "p@55w0rd!",
			"pm_addr": "192.168.24.206"
		}
	]
}

```

这个模板使用以下属性：

- name：节点的逻辑名称。
- pm_type：使用的电源管理驱动。
- pm_user：IPMI 的用户名
- pm_password：IPMI 的密码。
- pm_addr：IPMI 设备的 IP 地址。
- mac：节点上网络接口的 MAC 地址列表。（可选）对于每个系统的 Provisioning NIC，只使用 MAC 地址。
- cpu：节点上的 CPU 数量。（可选）
- memory：以 MB 为单位的内存大小。（可选）
- disk：以 GB 为单位的硬盘的大小。（可选）
- arch：系统架构。（可选）

创建完模板后，将这个文件保存到 `/home/stack/instackenv.json`，然后使用以下命令将其导入到 director：

```
(undercloud) [stack@undercloud ~]$ openstack overcloud node import ~/instackenv.json

```

完成节点注册和配置之后，可在 CLI 中查看这些节点的列表：

```
(undercloud) [stack@undercloud ~]$ openstack baremetal node list
+--------------------------------------+------+---------------+-------------+--------------------+-------------+
| UUID                                 | Name | Instance UUID | Power State | Provisioning State | Maintenance |
+--------------------------------------+------+---------------+-------------+--------------------+-------------+
| 9913d158-6d29-4a8e-a7b9-32ed0b6668b7 | None | None          | power off   | manageable         | False       |
| 9b862230-5a60-48ce-85c1-232922d03a05 | None | None          | power on    | manageable         | False       |
+--------------------------------------+------+---------------+-------------+--------------------+-------------+

```

### 3.2. 检查节点硬件

director 可以在每个节点上运行内省进程。这个进程会使每个节点通过 PXE 引导一个内省代理。这个代理从节点上收集硬件数据，并把信息发送回 director，director 把这些信息保存在运行于 director 上的 OpenStack Object Storage (swift) 服务中。

```
(undercloud) [stack@undercloud ~]$ openstack overcloud node introspect --all-manageable --provide

```

可能需要 15 分钟来检查所有节点。内省完成后，所有节点都会变为 available 状态。

### 3.3. 为节点添加标签

在注册并检查完每个节点的硬件后，需要为它们添加标签，加入特定的配置文件。这些配置文件标签会把节点和类型（flavor）相匹配，从而使类型分配到部署角色。

```
(undercloud) [stack@undercloud ~]$ openstack baremetal node set --property capabilities='profile:compute,boot_option:local' 66e67a20-61fc-43f4-b7ce-f03a74e217e1
(undercloud) [stack@undercloud ~]$ openstack baremetal node set --property capabilities='profile:control,boot_option:local' 77cc24b1-9432-4729-9e66-0daf87c9aaaa
(undercloud) [stack@undercloud ~]$ openstack overcloud profiles list
+--------------------------------------+-----------+-----------------+-----------------+-------------------+
| Node UUID                            | Node Name | Provision State | Current Profile | Possible Profiles |
+--------------------------------------+-----------+-----------------+-----------------+-------------------+
| 77cc24b1-9432-4729-9e66-0daf87c9aaaa | compute15 | active          | control         |                   |
| 66e67a20-61fc-43f4-b7ce-f03a74e217e1 | compute14 | active          | compute         |                   |
+--------------------------------------+-----------+-----------------+-----------------+-------------------+

```

### 3.4. 编辑 OVERCLOUD 环境文件

在 `/home/stack/templates/` 目录下创建 `node-info.yaml` 文件：

```
(undercloud) [stack@undercloud ~]$ cat > /home/stack/templates/node-info.yaml << EOF
parameter_defaults:
  OvercloudControllerFlavor: control
  OvercloudComputeFlavor: compute
  OvercloudCephStorageFlavor: ceph-storage
  ControllerCount: 1
  ComputeCount: 1
  CephStorageCount: 0
EOF

```

### 3.5. 部署 OVERCLOUD

```
(undercloud) [stack@undercloud ~]$ openstack overcloud deploy --templates \
    -e /home/stack/templates/node-info.yaml \
    -e /home/stack/templates/overcloud_images.yaml \
    -e /home/stack/templates/network-environment.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-isolation.yaml \
    --log-file overcloud_install.log

```

</div>
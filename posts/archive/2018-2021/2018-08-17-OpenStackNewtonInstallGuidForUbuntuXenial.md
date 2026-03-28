---
title: "OpenStack Newton Install Guid For Ubuntu Xenial"
date: 2018-08-17T15:46:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [19, 21, 27]
url: http://47.84.100.47/?p=226
---


<figure class="wp-block-image size-large"><img decoding="async" src="https://maxwellii.com/wp-content/uploads/2023/11/201808151634.png?w=1024" alt="" class="wp-image-227" /></figure>

<div class="wp-block-jetpack-markdown">
## 1. Overview

为了深入理解 OpenStack 4个核心组件：Keystone、Glance、Nova、Neutron，在两台 Ubuntu 16.04 虚拟机上进行 1+1 部署（1个 Controller Node，1个 Compute Node），针对部署中遇到的几个问题进行总结学习。官方部署教程请参照：[OpenStack Installation Tutorial for Ubuntu](https://docs.openstack.org/newton/install-guide-ubuntu/)

## 2. Virtual Machine

本次部署需要两台 Ubuntu 16.04 虚拟机：

- Cotroller：用于整个集群的控制，高可靠性要求。承载数据库（MySQL）、队列服务器（RabbitMQ）。设置一块虚拟硬盘和两块网卡，要求 eth0 接 External Network，eth1 接入 Management Network。
- Compute： Compute Node，高内存 + CPU + IO 消耗型节点。设置一块虚拟硬盘和两块网卡，要求同 Controller Node。

服务器推荐配置：

<table>
<thead>
<tr>
<th>Node</th>
<th>CPU</th>
<th>内存</th>
<th>存储</th>
</tr>
</thead>
<tbody>
<tr>
<td>Controller</td>
<td>4核</td>
<td>16GB</td>
<td>100GB</td>
</tr>
<tr>
<td>Compute</td>
<td>16核</td>
<td>64GB</td>
<td>300GB</td>
</tr>
</tbody>
</table>
**注：此处资源分配超出了实际物理资源，可根据实际分配资源。**

网络配置：

<table>
<thead>
<tr>
<th>Node</th>
<th>eth0</th>
<th>eth1</th>
</tr>
</thead>
<tbody>
<tr>
<td>———–</td>
<td>External Network</td>
<td>Management Network</td>
</tr>
<tr>
<td>Controller</td>
<td>192.168.1.11</td>
<td>10.0.0.11</td>
</tr>
<tr>
<td>Compute</td>
<td>192.168.1.21</td>
<td>10.0.0.21</td>
</tr>
<tr>
<td>Subnet Mask</td>
<td>255.255.255.0</td>
<td>255.255.255.0</td>
</tr>
<tr>
<td>Gateway</td>
<td>192.168.1.1</td>
<td>10.0.0.1</td>
</tr>
</tbody>
</table>
**注：由于实际网络限制，External Network 通过虚拟网卡 192.168.1.1 共享主机网络来访问外网**

由于本次部署只用于学习 OpenStack 的核心组件，对可靠性、计算能力、存储能力等没有具体需求，因此网络节点、存储节点不进行部署。

### 2.1. Install Ubuntu

利用 VMware 启动两台虚拟机，安装 Ubuntu 16.04 服务器版本镜像（尽量不要使用桌面版本）。虚拟机启动后，先使用 `sudo passwd root ` 命令创建 root 账户并登入。然后注释掉 `/etc/apt/source.list` 文件中 `deb cdrom` 那一行内容（deb cdrom 表示利用 CD 盘来更新 Ubuntu 源）。然后执行以下命令更新 Ubuntu 源。

```
$ apt-get update -y

```

目前虚拟机中还没有安装 ssh 服务，所以无法利用 Xshell 或其他工具进行远程登入。执行以下命令安装 ssh。

```
$ apt-get install -y openssh-server

```

如果想要直接使用 root 账户登录，可以修改 `/etc/ssh/sshd_config` 文件中的 `PermitRootLogin` 字段，将 without-password(表示只能使用 key 登入)改为 yes。然后重启 ssh 服务，就可以直接用 root 账户登入虚拟机了。

```
$ service ssh restart
$ setvice ssh status

```

### 2.2. Configure Network

登入虚拟机之后，先配置虚拟机网络。

#### 2.2.1. Update Nic Name

输入 `ifconfig -a` 查看，发现网卡名称由 udev 管理命名为 ens33 和 ens34，为了方便使用，将网卡名称修改为上述表格所示的 eth0、eth1。

修改 `/etc/default/grub` 文件，将

```
GRUB_CMDLINE_LINUX_DEFAULT=""
GRUB_CMDLINE_LINUX=""

```

改为

```
GRUB_CMDLINE_LINUX_DEFAULT="quiet splash"
GRUB_CMDLINE_LINUX="net.ifnames=0 biosdevname=0"

```

更新 Grub 文件：

```
$ update-grub
$ grub-mkconfig -o /boot/grub/grub.cfg  

```

修改 `/etc/network/interfaces` 文件中的网卡信息，如下：

```
# The primary network interface
auto eth0
iface eth0 inet static
address 192.168.1.11
netmask 255.255.255.0
gateway 192.168.1.1

auto eth1
iface eth1 inet static
address eth1 10.0.0.11
netmask 255.255.255.0

```

重启虚拟机，使 grub 和 网卡信息生效。

**注：以上步骤在 Controller Node、Compute Node 配置方法相同，注意修改 ip。**

#### 2.2.2. Configure Host

OpenStack 要求所有的节点主机之间都是通过 host 互信的，编辑所有节点主机的 host 文件，配置完成后在每台主机上可以 ping 通其他主机名。 在 `/etc/hosts` 文件中加入其他节点信息，并且初始掉除 `127.0.0.1` 之外的环回地址项。

```
127.0.0.1       localhost
#127.0.1.1      ubuntu

# config all nodes
10.0.0.11       controller
10.0.0.21       compute

```

### 2.3. Security

在部署过程中需要多个密码，如创建数据库、创建用户租户等等，因此本指南按照下表记录密码。

<table>
<thead>
<tr>
<th>Password name</th>
<th>Description</th>
<th>My Password</th>
</tr>
</thead>
<tbody>
<tr>
<td>Database password</td>
<td>Root password for the database</td>
<td>database</td>
</tr>
<tr>
<td>ADMIN_PASS</td>
<td>Password of user admin</td>
<td>admin</td>
</tr>
<tr>
<td>DEMO_PASS</td>
<td>Password of user demo</td>
<td>demo</td>
</tr>
<tr>
<td>GLANCE_DBPASS</td>
<td>Database password for Image service</td>
<td>glancedb</td>
</tr>
<tr>
<td>GLANCE_PASS</td>
<td>Password of Image service user glance</td>
<td>glance</td>
</tr>
<tr>
<td>KEYSTONE_DBPASS</td>
<td>Database password of Identity service</td>
<td>keystonedb</td>
</tr>
<tr>
<td>NEUTRON_DBPASS</td>
<td>Database password for the Networking service</td>
<td>neutrondb</td>
</tr>
<tr>
<td>NEUTRON_PASS</td>
<td>Password of Networking service user neutron</td>
<td>neutron</td>
</tr>
<tr>
<td>METADATA_SECRET</td>
<td>Password of metadata proxy shared secret</td>
<td>metadata</td>
</tr>
<tr>
<td>NOVA_DBPASS</td>
<td>Database password for Compute service</td>
<td>novadb</td>
</tr>
<tr>
<td>NOVA_PASS</td>
<td>Password of Compute service user nova</td>
<td>nova</td>
</tr>
<tr>
<td>RABBIT_PASS</td>
<td>Password of user guest of RabbitMQ</td>
<td>rabbit</td>
</tr>
</tbody>
</table>

## 3. OpenStack Environment

### 3.1. Network Time Protocol (NTP)

#### 3.1.1. Controller Node

1、安装 NTP：

```
$ apt-get install -y chrony

```

2、在 `/etc/chrony/chrony.conf ` 文件中增加以下内容：

```
allow 10.0.0.0/24
server 127.127.1.0 iburst

```

3、重启 NTP 使配置生效：

```
$ service chrony restart 

```

#### 3.1.2. Other Node

1、安装 NTP：

```
$ apt-get install -y chrony

```

2、打开 `/etc/chrony/chrony.conf ` 文件，删除全部默认设置，只添加如下内容：

```
server controller iburst

```

3、重启 NTP 使配置生效：

```
$ service chrony restart 

```

#### 3.1.3. Verify Operation

分别在 Controller Node 和 Compute Node 上运行 `chronyc sources`。

```
root@controller:~# chronyc sources
210 Number of sources = 1
MS Name/IP address         Stratum Poll Reach LastRx Last sample
===============================================================================
^? 127.127.1.0                   0  10     0   10y     +0ns[   +0ns] +/-    0ns

```

```
root@compute:~# chronyc sources
210 Number of sources = 1
MS Name/IP address         Stratum Poll Reach LastRx Last sample
===============================================================================
^? controller                    0  10     0   10y     +0ns[   +0ns] +/-    0ns

```

### 3.2. OpenStack Packages（All Nodes）

#### 3.2.1. Enable The OpenStack Repository

配置 OpenStack Newton 源：

```
$ apt install software-properties-common
$ add-apt-repository cloud-archive:newton

```

#### 3.2.2. Finalize The Installation

1、更新源：

```
$ apt update && apt dist-upgrade

```

2、安装 OpenStack Client：

```
$ apt install python-openstackclient

```

### 3.3. SQL Database（Controller Only）

大部分 OpenStack 服务使用数据库来存储信息，数据库通常部署在 Controller Node 上。本分根据分配使用 MariaDB 或者 MySQL。

#### 3.3.1. Install And Configure Mariadb

1、安装 mariadb-server 和 python-pymysql：

```
$ apt install mariadb-server python-pymysql

```

2、配置数据库，创建文件 `/etc/mysql/mariadb.conf.d/99-openstack.cnf`，添加如下配置信息：

```
[mysqld]
bind-address = 10.0.0.11

default-storage-engine = innodb
innodb_file_per_table
max_connections = 4096
collation-server = utf8_general_ci
character-set-server = utf8

```

bind-address 用于绑定 MySQL 服务监听地址到 Controller Node 的管理网络网口 IP,以便其他节点访问 MySQL 中 OpenStack 的配置信息。

#### 3.3.2. Finalize Installation

1、重启数据库服务：

```
$ service mysql restart
$ service mysql status

```

2、查看 3306 端口是否监听：

```
root@controller:~# netstat -ntlp | grep 3306
tcp        0      0 10.0.0.11:3306          0.0.0.0:*               LISTEN      9424/mysqld

```

3、创建数据库账户，运行 `mysql_secure_installation` 脚本来保护数据库服务，用前面设计的密码创建 root 帐户。

### 3.4. Message Queue（Controller Only）

OpenStack 使用消息队列来协调服务之间的操作和状态信息。消息队列服务通常在控制器节点上运行。 OpenStack 支持多种消息队列服务，本指南使用 RabbitMQ 消息队列服务。

1、安装 rabbitmq-server：

```
$ apt install rabbitmq-server

```

2、创建 OpenStack 用户：

```
$ rabbitmqctl add_user openstack RABBIT_PASS

```

**注：将 `RABBIT_PASS` 替换为前面设计的实际密码。**

3、配置读、写权限：

```
$ rabbitmqctl set_permissions openstack ".*" ".*" ".*"

```

### 3.5. Memcached（Controller Only）

身份验证服务使用 Memcached 来缓存令牌，通常在控制器节点上运行。

#### 3.5.1. Install And Configure Memcached

1、安装 memcached 和 python-memcache：

```
$ apt install memcached python-memcache

```

2、配置监听地址，修改 `/etc/memcached.conf` 配置文件,设置 Memcached 服务监听地址为 Controller Node 的 Management Network IP。

```
-l 10.0.0.11

```

#### 3.5.2. Finalize Installation

1、重启 memcached 服务：

```
service memcached restart
service memcached status

```

2、检查 11211 端口是否监听：

```
root@controller:~# netstat -ntlp | grep 11211
tcp        0      0 10.0.0.11:11211         0.0.0.0:*               LISTEN      11382/memcached 

```

## 4. Identity Service

### 4.1. Identity Service Overview

Keystone 是 OpenStack Identity Service 的项目名称，负责身份验证、管理规则和令牌的功能。项目 WIKI：[Keystone WIKI](https://wiki.openstack.org/wiki/Keystone)

### 4.2. Install And Configure Controller Node

#### 4.2.1. Prerequisites

1、使用 root 账户登入数据库：

```
$ mysql -u root -p

```

2、创建数据库：

```
MariaDB [(none)]> CREATE DATABASE keystone;

```

3、数据库授权：

```
MariaDB [(none)]> GRANT ALL PRIVILEGES ON keystone.* TO 'keystone'@'localhost' \
  IDENTIFIED BY 'KEYSTONE_DBPASS';
MariaDB [(none)]> GRANT ALL PRIVILEGES ON keystone.* TO 'keystone'@'%' \
  IDENTIFIED BY 'KEYSTONE_DBPASS';

```

**注：将 `KEYSTONE_DBPASS` 替换为前面设计的实际密码。**
**注：上述授权命令中，% 代表了所有的 host 都能远程访问该 mysql。但 MySQL 官方文档指出，% 并不包括 localhost。因此需要对 localhost 和 % 都进行授权。**

4、验证 keystone 用户能否正常登录：

```
$ mysql -h localhost -u keystone -p
$ mysql -h controller -u keystone -p

```

#### 4.2.2. Install And Configure Keystone

1、安装 Keystone：

```
$ apt install keystone

```

2、修改 `/etc/keystone/keystone.conf` 配置文件：

在 `[database]` 部分，配置数据库访问连接：

```
[database]
...
connection = mysql+pymysql://keystone:KEYSTONE_DBPASS@controller/keystone

```

**注：将 `KEYSTONE_DBPASS` 替换为前面设计的实际密码。**

在 `[token]` 部分，配置 Fernet 令牌：

```
[token]
...
provider = fernet

```

**注：Keystone 支持 UUID、PKI、PKIZ、Fernet 四种令牌，关于令牌介绍请见：[Keystone’s Token](https://maxwellii.com/post/limaxwell93.wordpress.com/57)**

3、将配置信息写入到 Keystone 数据库：

```
$ su -s /bin/sh -c "keystone-manage db_sync" keystone

```

4、 初始化 Fernet Key：

```
$ keystone-manage fernet_setup --keystone-user keystone --keystone-group keystone
$ keystone-manage credential_setup --keystone-user keystone --keystone-group keystone

```

5、 引导 Identity Service

```
$ keystone-manage bootstrap --bootstrap-password ADMIN_PASS \
  --bootstrap-admin-url http://controller:35357/v3/ \
  --bootstrap-internal-url http://controller:35357/v3/ \
  --bootstrap-public-url http://controller:5000/v3/ \
  --bootstrap-region-id RegionOne

```

**注：将 `ADMIN_PASS` 替换为前面设计的实际密码。**

#### 4.2.3. Configure The Apache HTTP Server

修改 `/etc/apache2/apache2.conf` 文件，配置 ServerName：

```
ServerName controller

```

#### 4.2.4. Finalize The Installation

1、重启 Apache 服务，并删除 Keystone 配置信息默认数据库：

```
$ service apache2 restart
$ rm -f /var/lib/keystone/keystone.db

```

**注：Ubuntu 安装 Keystone 时默认配置采用 SQLite 数据库存放，但本指南使用 MySQL 数据库存储 Keystone 配置信息，因此可删除默认 SQLite 数据库。**

2、检查端口是否监听：

```
root@controller:~# netstat -natp | grep apache
tcp6       0      0 :::80                   :::*                    LISTEN      22522/apache2   
tcp6       0      0 :::35357                :::*                    LISTEN      22522/apache2   
tcp6       0      0 :::5000                 :::*                    LISTEN      22522/apache2   

```

3、设置 Admin 账户：

```
$ export OS_USERNAME=admin
$ export OS_PASSWORD=ADMIN_PASS
$ export OS_PROJECT_NAME=admin
$ export OS_USER_DOMAIN_NAME=Default
$ export OS_PROJECT_DOMAIN_NAME=Default
$ export OS_AUTH_URL=http://controller:35357/v3
$ export OS_IDENTITY_API_VERSION=3

```

**注：将 `ADMIN_PASS` 替换为引导 Identity Service 时设置的密码。**

### 4.3. Create Domain Projects Users Roles

Identity Service 使用 Domains、Projects、Users、Roles 组合为每个 OpenStack Services 提供鉴权服务。

1、创建 Service Project：

```
root@controller:~# openstack project create \
>   --domain default \
>   --description "Service Project" \
>   service

+-------------+----------------------------------+
| Field       | Value                            |
+-------------+----------------------------------+
| description | Service Project                  |
| domain_id   | default                          |
| enabled     | True                             |
| id          | 719b10c4966f4d26a1d63cfe0b645d27 |
| is_domain   | False                            |
| name        | service                          |
| parent_id   | default                          |
+-------------+----------------------------------+

```

2、创建 Demo Project：

```
root@controller:~# openstack project create \
>   --domain default \
>   --description "Demo Project" \
>   demo

+-------------+----------------------------------+
| Field       | Value                            |
+-------------+----------------------------------+
| description | Demo Project                     |
| domain_id   | default                          |
| enabled     | True                             |
| id          | d5ab5565fbee4a67a34e0d6b98845608 |
| is_domain   | False                            |
| name        | demo                             |
| parent_id   | default                          |
+-------------+----------------------------------+

```

3、创建 Demo User：

```
root@controller:~# openstack user create \
>   --domain default \
>   --password-prompt demo

User Password:
Repeat User Password:
+---------------------+----------------------------------+
| Field               | Value                            |
+---------------------+----------------------------------+
| domain_id           | default                          |
| enabled             | True                             |
| id                  | 58d4bbf89ef94aa89fe44e6310fe37cb |
| name                | demo                             |
| password_expires_at | None                             |
+---------------------+----------------------------------+

```

**注：使用前面设计的 Demo user 密码。**

4、创建 User Role：

```
root@controller:~# openstack role create user

+-----------+----------------------------------+
| Field     | Value                            |
+-----------+----------------------------------+
| domain_id | None                             |
| id        | 8b0afeefc8944bca9e968ed196d1d9f6 |
| name      | user                             |
+-----------+----------------------------------+

```

5、将 User Role 授予 Demo User 和 Demo Project：

```
$ openstack role add --project demo --user demo user

```

### 4.4. Verify Operation

1、禁用临时身份验证令牌。

修改 `/etc/keystone/keystone-paste.ini` 文件，在 `[pipeline:public_api]` `[pipeline:admin_api]` 和 `[pipeline:api_v3]` 部分中，删除 `admin_token_auth`。

2、取消 `OS_AUTH_URL` 和 `OS_PASSWORD` 环境变量。

```
$ unset OS_AUTH_URL OS_PASSWORD

```

3、使用 Admin User 申请一个身份认证令牌：

```
root@controller:~# openstack --os-auth-url http://controller:35357/v3 \
>   --os-project-domain-name Default \
>   --os-user-domain-name Default \
>   --os-project-name admin \
>   --os-username admin \
>   token issue

Password: 
+------------+-----------------------------------------------------------------------------------------------------------------------------+
| Field      | Value                                                                                                                       |
+------------+-----------------------------------------------------------------------------------------------------------------------------+
| expires    | 2018-08-10 08:07:14+00:00                                                                                                   |
| id         | gAAAAABbbTmiD3Wjp0-xLcnxRrIXrJqSLXMVHkgoOi8407hy4K6YU7nI4EbxhX9Rbz68lfZ-mjV4tmWlL7R_Txh-                                    |
|            | p3j5pZTg0sNARoqU_dUH4EpoaWY9c0aBDlGYZ0EbDXgumbq6gIe_zxw5LaE5EGoNYx8z0Yfte5V0Jr1YPYX2rw2ZXIP4Uco                             |
| project_id | a0032382f4024e409f236fe922d2ee8f                                                                                            |
| user_id    | 3082e8e6887e47ccac419b9b2b9336f5                                                                                            |
+------------+-----------------------------------------------------------------------------------------------------------------------------+

```

4、使用 Demo User 申请一个身份认证令牌：

```
root@controller:~# openstack --os-auth-url http://controller:5000/v3 \
>   --os-project-domain-name Default \
>   --os-user-domain-name Default \
>   --os-project-name demo \
>   --os-username demo \
>   token issue

Password: 
+------------+-----------------------------------------------------------------------------------------------------------------------------+
| Field      | Value                                                                                                                       |
+------------+-----------------------------------------------------------------------------------------------------------------------------+
| expires    | 2018-08-10 08:09:12+00:00                                                                                                   |
| id         | gAAAAABbbToYyjvq2h-wQVZjXLwK6jWzfmqjzILD7m8FeL9gkvtjNtKtrQexAYRhdnSY2RvOzNqygUjQIZMVCvTgpZFIsuD_cH6Cr12TB5uM-               |
|            | fQH88tLaqP1tEUPsN-ABFwL1lrS2oqxTAW27kFShNS1c4GomIYrTfgk6mhK-8U04A-1BQOc6_g                                                  |
| project_id | d5ab5565fbee4a67a34e0d6b98845608                                                                                            |
| user_id    | 58d4bbf89ef94aa89fe44e6310fe37cb                                                                                            |
+------------+-----------------------------------------------------------------------------------------------------------------------------+

```

5、验证 Identity Service 是否正常。在其他节点访问 Identity Service API 路径：

```
curl http://192.168.1.11:35357/v3
curl http://192.168.1.11:5000/v3
curl http://controller:35357/v3
curl http://controller:5000/v3

```

得到如下信息：

```
{
    "version":{
        "status":"stable",
        "updated":"2016-10-06T00:00:00Z",
        "media-types":[
            {
                "base":"application/json",
                "type":"application/vnd.openstack.identity-v3+json"
            }
        ],
        "id":"v3.7",
        "links":[
            {
                "href":"http://192.168.1.11:35357/v3/",
                "rel":"self"
            }
        ]
    }
}

```

### 4.5. Create OpenStack Client Environment Scripts

1、为 Admin User 创建 OpenStack Client 环境脚本，将以下内容添加到 `admin-openrc`。

```
export OS_PROJECT_DOMAIN_NAME=Default
export OS_USER_DOMAIN_NAME=Default
export OS_PROJECT_NAME=admin
export OS_USERNAME=admin
export OS_PASSWORD=ADMIN_PASS
export OS_AUTH_URL=http://controller:35357/v3
export OS_IDENTITY_API_VERSION=3
export OS_IMAGE_API_VERSION=2

```

**注：将 `ADMIN_PASS` 替换为前面设计的实际密码。**

2、为 Demo User 创建 OpenStack Client 环境脚本，将以下内容添加到 `demo-openrc`。

```
export OS_PROJECT_DOMAIN_NAME=Default
export OS_USER_DOMAIN_NAME=Default
export OS_PROJECT_NAME=demo
export OS_USERNAME=demo
export OS_PASSWORD=DEMO_PASS
export OS_AUTH_URL=http://controller:5000/v3
export OS_IDENTITY_API_VERSION=3
export OS_IMAGE_API_VERSION=2

```

**注：将 `DEMO_PASS` 替换为前面设计的实际密码。**

运行 `admin-openrc` 或 `demo-openrc` 脚本，就可以用特定的用户来使用 OpenStack Client。

## 5. Image Service

### 5.1. Image Service Overview

OpenStack Image Service 允许用户发现、注册和获取虚拟机镜像。它提供了一个 REST API，允许您查询虚拟机镜像的 metadata 并获取一个现存的镜像。它能够接受磁盘镜像或服务器镜像的 API 请求，和来自终端用户或 OpenStack 计算组件的元数据定义。项目 WIKI：[Glance WIKI](https://wiki.openstack.org/wiki/Glance)

### 5.2. Install And Configure Controller Node

#### 5.2.1. Prerequisites

1、使用 root 账户登入数据库：

```
$ mysql -u root -p

```

2、创建数据库：

```
MariaDB [(none)]> CREATE DATABASE glance;

```

3、数据库授权：

```
MariaDB [(none)]> GRANT ALL PRIVILEGES ON glance.* TO 'glance'@'localhost' \
  IDENTIFIED BY 'GLANCE_DBPASS';
MariaDB [(none)]> GRANT ALL PRIVILEGES ON glance.* TO 'glance'@'%' \
  IDENTIFIED BY 'GLANCE_DBPASS';

```

**注：将 `GLANCE_DBPASS` 替换为前面设计的实际密码。**

4、设置 OpenStack 中 Admin User 环境变量：

```
$ source ~/openstack/admin-openrc

```

5、创建 Glance User：

```
root@controller:~# openstack user create \
>   --domain default \
>   --password-prompt glance

User Password:
Repeat User Password:
+---------------------+----------------------------------+
| Field               | Value                            |
+---------------------+----------------------------------+
| domain_id           | default                          |
| enabled             | True                             |
| id                  | 2825d50da19542c6a1cbb33af5e21ddd |
| name                | glance                           |
| password_expires_at | None                             |
+---------------------+----------------------------------+

```

6、将 Admin Role 授予 Glance User 和 Service Project：

```
$ openstack role add --project service --user glance admin

```

7、创建 Glance Service：

```
root@controller:~# openstack service create \
>   --name glance \
>   --description "OpenStack Image" \
>   image
+-------------+----------------------------------+
| Field       | Value                            |
+-------------+----------------------------------+
| description | OpenStack Image                  |
| enabled     | True                             |
| id          | 8bd49a8420b0432a9219f36a4b3efc2e |
| name        | glance                           |
| type        | image                            |
+-------------+----------------------------------+

```

8、创建 Image Service API endpoints:

```
root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   image public http://controller:9292

+--------------+----------------------------------+
| Field        | Value                            |
+--------------+----------------------------------+
| enabled      | True                             |
| id           | 11c6771782674a518db79ab2faab18bf |
| interface    | public                           |
| region       | RegionOne                        |
| region_id    | RegionOne                        |
| service_id   | 8bd49a8420b0432a9219f36a4b3efc2e |
| service_name | glance                           |
| service_type | image                            |
| url          | http://controller:9292           |
+--------------+----------------------------------+

root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   image internal http://controller:9292

+--------------+----------------------------------+
| Field        | Value                            |
+--------------+----------------------------------+
| enabled      | True                             |
| id           | 383ed2316a814706abc7f72a2a1309a2 |
| interface    | internal                         |
| region       | RegionOne                        |
| region_id    | RegionOne                        |
| service_id   | 8bd49a8420b0432a9219f36a4b3efc2e |
| service_name | glance                           |
| service_type | image                            |
| url          | http://controller:9292           |
+--------------+----------------------------------+

root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   image admin http://controller:9292

+--------------+----------------------------------+
| Field        | Value                            |
+--------------+----------------------------------+
| enabled      | True                             |
| id           | 956fecb48b214d4c91d7a9edd9477d92 |
| interface    | admin                            |
| region       | RegionOne                        |
| region_id    | RegionOne                        |
| service_id   | 8bd49a8420b0432a9219f36a4b3efc2e |
| service_name | glance                           |
| service_type | image                            |
| url          | http://controller:9292           |
+--------------+----------------------------------+

```

#### 5.2.2. Install And Configure Glance

1、安装 Glance：

```
$ apt install glance

```

2、修改 `/etc/glance/glance-api.conf` 配置文件：

在 `[database]` 部分，配置数据库访问连接：

```
[database]
...
connection = mysql+pymysql://glance:GLANCE_DBPASS@controller/glance

```

**注：将 `GLANCE_DBPASS` 替换为前面设计的实际密码。**

在 `[keystone_authtoken]` 和 `[paste_deploy]` 部分，配置身份服务访问：

```
[keystone_authtoken]
...
auth_uri = http://controller:5000
auth_url = http://controller:35357
memcached_servers = controller:11211
auth_type = password
project_domain_name = Default
user_domain_name = Default
project_name = service
username = glance
password = GLANCE_PASS

[paste_deploy]
...
flavor = keystone

```

**注：将 `GLANCE_PASS` 替换为前面设计的实际密码。**

在 `[glance_store]` 处配置本地文件系统和镜像文件存储位置:

```
[glance_store]
...
stores = file,http
default_store = file
filesystem_store_datadir = /var/lib/glance/images/

```

3、修改 `/etc/glance/glance-registry.conf` 配置文件：

在 `[database]` 部分，配置数据库访问连接：

```
[database]
...
connection = mysql+pymysql://glance:GLANCE_DBPASS@controller/glance

```

**注：将 `GLANCE_DBPASS` 替换为前面设计的实际密码。**

在 `[keystone_authtoken]` 和 `[paste_deploy]` 部分，配置身份服务访问：

```
[keystone_authtoken]
...
auth_uri = http://controller:5000
auth_url = http://controller:35357
memcached_servers = controller:11211
auth_type = password
project_domain_name = Default
user_domain_name = Default
project_name = service
username = glance
password = GLANCE_PASS

[paste_deploy]
...
flavor = keystone

```

**注：将 `GLANCE_PASS` 替换为前面设计的实际密码。**

4、将配置信息写入 Glance 数据库：

```
$ su -s /bin/sh -c "glance-manage db_sync" glance

```

#### 5.2.3. Finalize Installation

重启 Image Service：

```
$ service glance-registry restart
$ service glance-api restart

```

### 5.3. Verify Operation

1、设置 OpenStack 中 Admin User 环境变量：

```
$ source ~/openstack/admin-openrc

```

2、下载 CirrOS 系统镜像：

```
$ wget http://download.cirros-cloud.net/0.3.4/cirros-0.3.4-x86_64-disk.img

```

3、上传镜像，设置磁盘格式 QCOW2、容器格式 bare 及可见性 public：

```
root@controller:~# openstack image create "cirros" \
>   --file cirros-0.3.4-x86_64-disk.img \
>   --disk-format qcow2 \
>   --container-format bare \
>   --public

+------------------+------------------------------------------------------+
| Field            | Value                                                |
+------------------+------------------------------------------------------+
| checksum         | ee1eca47dc88f4879d8a229cc70a07c6                     |
| container_format | bare                                                 |
| created_at       | 2018-08-10T08:53:29Z                                 |
| disk_format      | qcow2                                                |
| file             | /v2/images/54cd1762-36c9-4ecd-b3ac-793a19c6f796/file |
| id               | 54cd1762-36c9-4ecd-b3ac-793a19c6f796                 |
| min_disk         | 0                                                    |
| min_ram          | 0                                                    |
| name             | cirros                                               |
| owner            | a0032382f4024e409f236fe922d2ee8f                     |
| protected        | False                                                |
| schema           | /v2/schemas/image                                    |
| size             | 13287936                                             |
| status           | active                                               |
| tags             |                                                      |
| updated_at       | 2018-08-10T08:53:30Z                                 |
| virtual_size     | None                                                 |
| visibility       | public                                               |
+------------------+------------------------------------------------------+

```

关于磁盘和容器的镜像格式，可参考：[Disk and container formats for images](https://docs.openstack.org/image-guide/image-formats.html)。

## 6. Compute Service

OpenStack Compute Service 负责管理所有 Instance， 它与其他几个 OpenStack Service 都有一些接口：使用 Keystone 来执行其身份验证，使用 Horizon 作为其管理接口，并用 Glance 提供其镜像。项目 WIKI：[Nova WIKI](https://wiki.openstack.org/wiki/Nova)

### 6.1. Compute Service Overview

### 6.2. Install And Configure Controller Node

#### 6.2.1. Prerequisites

1、使用 root 账户登入数据库：

```
$ mysql -u root -p

```

2、创建数据库：

```
MariaDB [(none)]> CREATE DATABASE nova_api;
MariaDB [(none)]> CREATE DATABASE nova;

```

3、数据库授权：

```
MariaDB [(none)]> GRANT ALL PRIVILEGES ON nova_api.* TO 'nova'@'localhost' \
  IDENTIFIED BY 'NOVA_DBPASS';
MariaDB [(none)]> GRANT ALL PRIVILEGES ON nova_api.* TO 'nova'@'%' \
  IDENTIFIED BY 'NOVA_DBPASS';
MariaDB [(none)]> GRANT ALL PRIVILEGES ON nova.* TO 'nova'@'localhost' \
  IDENTIFIED BY 'NOVA_DBPASS';
MariaDB [(none)]> GRANT ALL PRIVILEGES ON nova.* TO 'nova'@'%' \
  IDENTIFIED BY 'NOVA_DBPASS';

```

**注：将 `NOVA_DBPASS` 替换为前面设计的实际密码。**

4、设置 OpenStack 中 Admin User 环境变量：

```
$ source ~/openstack/admin-openrc

```

5、创建 Nova User：

```
root@controller:~# openstack user create \
>   --domain default \
>   --password-prompt nova

User Password:
Repeat User Password:
+---------------------+----------------------------------+
| Field               | Value                            |
+---------------------+----------------------------------+
| domain_id           | default                          |
| enabled             | True                             |
| id                  | 5e97e43ea09e4b2389adbe0a40e8e38c |
| name                | nova                             |
| password_expires_at | None                             |
+---------------------+----------------------------------+

```

6、将 Admin Role 授予 Nova User 和 Service Project：

```
$ openstack role add --project service --user nova admin

```

7、创建 Nova Service：

```
root@controller:~# openstack service create \
>   --name nova \
>   --description "OpenStack Compute" \
>   compute

+-------------+----------------------------------+
| Field       | Value                            |
+-------------+----------------------------------+
| description | OpenStack Compute                |
| enabled     | True                             |
| id          | 0dffd32dd9b8412dadb7b872a51e2484 |
| name        | nova                             |
| type        | compute                          |
+-------------+----------------------------------+

```

8、创建 Compute Service API endpoints:

```
root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   compute public http://controller:8774/v2.1/%\(tenant_id\)s

+--------------+-------------------------------------------+
| Field        | Value                                     |
+--------------+-------------------------------------------+
| enabled      | True                                      |
| id           | f7d66a85561e4256afd0735cb4740293          |
| interface    | public                                    |
| region       | RegionOne                                 |
| region_id    | RegionOne                                 |
| service_id   | 0dffd32dd9b8412dadb7b872a51e2484          |
| service_name | nova                                      |
| service_type | compute                                   |
| url          | http://controller:8774/v2.1/%(tenant_id)s |
+--------------+-------------------------------------------+

root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   compute internal http://controller:8774/v2.1/%\(tenant_id\)s

+--------------+-------------------------------------------+
| Field        | Value                                     |
+--------------+-------------------------------------------+
| enabled      | True                                      |
| id           | 39ec1494ccf94f279b709c6038eb7d5e          |
| interface    | internal                                  |
| region       | RegionOne                                 |
| region_id    | RegionOne                                 |
| service_id   | 0dffd32dd9b8412dadb7b872a51e2484          |
| service_name | nova                                      |
| service_type | compute                                   |
| url          | http://controller:8774/v2.1/%(tenant_id)s |
+--------------+-------------------------------------------+

root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   compute admin http://controller:8774/v2.1/%\(tenant_id\)s

+--------------+-------------------------------------------+
| Field        | Value                                     |
+--------------+-------------------------------------------+
| enabled      | True                                      |
| id           | f9f6b362b4514ca5a58131fd1472c749          |
| interface    | admin                                     |
| region       | RegionOne                                 |
| region_id    | RegionOne                                 |
| service_id   | 0dffd32dd9b8412dadb7b872a51e2484          |
| service_name | nova                                      |
| service_type | compute                                   |
| url          | http://controller:8774/v2.1/%(tenant_id)s |
+--------------+-------------------------------------------+

```

#### 6.2.2. Install And Configure Nova

1、安装 Nova：

```
$ apt install nova-api nova-conductor nova-consoleauth nova-novncproxy nova-scheduler

```

2、修改 `/etc/nova/nova.conf` 配置文件：

在 `[api_database]` 和 `[database]` 部分，配置数据库访问连接：

```
[api_database]
connection = mysql+pymysql://nova:NOVA_DBPASS@controller/nova_api

[database]
connection = mysql+pymysql://nova:NOVA_DBPASS@controller/nova

```

**注：将 `NOVA_DBPASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 部分，配置 RabbitMQ 消息队列访问：

```
[DEFAULT]
...
transport_url = rabbit://openstack:RABBIT_PASS@controller

```

**注：将 `RABBIT_PASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 和 `[keystone_authtoken]` 部分，配置身份服务访问：

```
[DEFAULT]
...
auth_strategy = keystone

[keystone_authtoken]
auth_uri = http://controller:5000
auth_url = http://controller:35357
memcached_servers = controller:11211
auth_type = password
project_domain_name = Default
user_domain_name = Default
project_name = service
username = nova
password = NOVA_PASS

```

**注：将 `NOVA_PASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 部分配置 `my_ip` 为 Controller 节点 Management Network 网络 ip：

```
[DEFAULT]
...
my_ip = 10.0.0.11

```

在 `[DEFAULT]` 部分启用网络服务支持：

```
[DEFAULT]
...
use_neutron = True
firewall_driver = nova.virt.firewall.NoopFirewallDriver

```

**注：默认情况下，Compute Service 使用主机内部防火墙驱动，因此必须禁用 OpenStack 网络服务中的防火墙驱动。**

在 `[vnc]` 部分，使用 Controller 节点 Management Network 网络地址配置 VNC Proxy：

```
[vnc]
vncserver_listen = $my_ip
vncserver_proxyclient_address = $my_ip

```

在 `[glance]` 部分置 Image Service API 路径：

```
[glance]
api_servers = http://controller:9292

```

在 `[oslo_concurrency]` 部分，配置 lock path：

```
[oslo_concurrency]
lock_path = /var/lib/nova/tmp

```

由于安装包 BUG，需要从 `[DEFAULT]` 部分移除 `log-dir` 那一行配置。

3、将配置信息写入 Nova 数据库：

```
$ su -s /bin/sh -c "nova-manage api_db sync" nova
$ su -s /bin/sh -c "nova-manage db sync" nova

```

#### 6.2.3. Finalize Installation

重启 Compute Service：

```
$ service nova-api restart
$ service nova-consoleauth restart
$ service nova-scheduler restart
$ service nova-conductor restart
$ service nova-novncproxy restart

```

### 6.3. Install And Configure Compute Node

#### 6.3.1. Install And Configure Nova

1、安装 Nova：

```
$ apt install nova-compute

```

2、修改 `/etc/nova/nova.conf` 配置文件：

在 `[DEFAULT]` 部分，配置 RabbitMQ 消息队列访问：

```
[DEFAULT]
...
transport_url = rabbit://openstack:RABBIT_PASS@controller

```

**注：将 `RABBIT_PASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 和 `[keystone_authtoken]` 部分，配置身份服务访问：

```
[DEFAULT]
...
auth_strategy = keystone

[keystone_authtoken]
auth_uri = http://controller:5000
auth_url = http://controller:35357
memcached_servers = controller:11211
auth_type = password
project_domain_name = Default
user_domain_name = Default
project_name = service
username = nova
password = NOVA_PASS

```

**注：将 `NOVA_PASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 部分配置 `my_ip` 为 Compute 节点 Management Network 网络 ip：

```
[DEFAULT]
...
my_ip = 10.0.0.21

```

在 `[DEFAULT]` 部分启用网络服务支持：

```
[DEFAULT]
...
use_neutron = True
firewall_driver = nova.virt.firewall.NoopFirewallDriver

```

**注：默认情况下，Compute Service 使用主机内部防火墙驱动，因此必须禁用 OpenStack 网络服务中的防火墙驱动。**

在 `[vnc]` 部分，配置远程控制访问：

```
[vnc]
enabled = True
vncserver_listen = 0.0.0.0
vncserver_proxyclient_address = $my_ip
novncproxy_base_url = http://controller:6080/vnc_auto.html

```

**注： VNC Server 监听所有地址，VNC Proxy 只监听 Compute 节点 Management Network 网络地址。Base URL 设置 Compute 节点远程控制台浏览器访问地址。**

在 `[glance]` 部分置 Image Service API 路径：

```
[glance]
api_servers = http://controller:9292

```

在 `[oslo_concurrency]` 部分，配置 lock path：

```
[oslo_concurrency]
lock_path = /var/lib/nova/tmp

```

由于安装包 BUG，需要从 `[DEFAULT]` 部分移除 `log-dir` 那一行配置。

#### 6.3.2. Finalize Installation

1、检测是否支持虚拟机硬件加速：

```
$ egrep -c '(vmx|svm)' /proc/cpuinfo

```

返回值：

- 1：代表支持硬件加速，无需额外配置。
- 0：代表不支持硬件加速，需要修改 `/etc/nova/nova-compute.conf` 配置文件，使用 QEMU 代替 KVM。

```
[libvirt]
virt_type = qemu

```

2、重启 Compute Service：

```
$ service nova-compute restart

```

### 6.4. Verify Operation

**注：以下步骤需在 Controller 节点执行。**

1、设置 OpenStack 中 Admin User 环境变量：

```
$ source ~/openstack/admin-openrc

```

2、打印 Compute Service 组件列表，所有进程启动成功：

```
root@controller:~# openstack compute service list
+----+------------------+------------+----------+---------+-------+----------------------------+
| ID | Binary           | Host       | Zone     | Status  | State | Updated At                 |
+----+------------------+------------+----------+---------+-------+----------------------------+
|  3 | nova-consoleauth | controller | internal | enabled | up    | 2018-08-13T05:48:33.000000 |
|  4 | nova-scheduler   | controller | internal | enabled | up    | 2018-08-13T05:48:34.000000 |
|  5 | nova-conductor   | controller | internal | enabled | up    | 2018-08-13T05:48:37.000000 |
|  6 | nova-compute     | compute    | nova     | enabled | up    | 2018-08-13T05:48:35.000000 |
+----+------------------+------------+----------+---------+-------+----------------------------+

```

## 7. Networking Service

### 7.1. Networking Service Overview

OpenStack Networking Service 负责管理 OpenStack 环境中所有虚拟网络基础设施（VNI）和物理网络基础设施（PNI）的接入层。OpenStack Networking Service 允许 Project 创建包括像 firewall、load balancer、virtual private network (VPN)等高级虚拟网络拓扑。Networking Service 提供三个抽象概念： networks、subnets 和 routers。每个抽象概念都有自己的功能，可以模拟对应的物理设备。项目 WIKI：[Neutron WIKI](https://wiki.openstack.org/wiki/Neutron)

### 7.2. Install And Configure Controller Node

#### 7.2.1. Prerequisites

1、使用 root 账户登入数据库：

```
$ mysql -u root -p

```

2、创建数据库：

```
MariaDB [(none)]> CREATE DATABASE neutron;

```

3、数据库授权：

```
MariaDB [(none)]> GRANT ALL PRIVILEGES ON neutron.* TO 'neutron'@'localhost' \
  IDENTIFIED BY 'NEUTRON_DBPASS';
MariaDB [(none)]> GRANT ALL PRIVILEGES ON neutron.* TO 'neutron'@'%' \
  IDENTIFIED BY 'NEUTRON_DBPASS';

```

**注：将 `NEUTRON_DBPASS` 替换为前面设计的实际密码。**

4、设置 OpenStack 中 Admin User 环境变量：

```
$ source ~/openstack/admin-openrc

```

5、创建 Neutron User：

```
root@controller:~# openstack user create \
>   --domain default \
>   --password-prompt neutron

User Password:
Repeat User Password:
+---------------------+----------------------------------+
| Field               | Value                            |
+---------------------+----------------------------------+
| domain_id           | default                          |
| enabled             | True                             |
| id                  | 0bc83b0d860d48c28e4dffd45acd63b3 |
| name                | neutron                          |
| password_expires_at | None                             |
+---------------------+----------------------------------+

```

6、将 Admin Role 授予 Neutron User 和 Service Project：

```
$ openstack role add --project service --user neutron admin

```

7、创建 Neutron Service：

```
root@controller:~# openstack service create --name neutron \
>   --description "OpenStack Networking" \
>   network

+-------------+----------------------------------+
| Field       | Value                            |
+-------------+----------------------------------+
| description | OpenStack Networking             |
| enabled     | True                             |
| id          | 5e7ffc56d8aa4e3d811cff62d57dc806 |
| name        | neutron                          |
| type        | network                          |
+-------------+----------------------------------+

```

8、创建 Networking Service API endpoints:

```
root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   network public http://controller:9696

+--------------+----------------------------------+
| Field        | Value                            |
+--------------+----------------------------------+
| enabled      | True                             |
| id           | 93fedcbc07d54c359e203fe58d7b81ab |
| interface    | public                           |
| region       | RegionOne                        |
| region_id    | RegionOne                        |
| service_id   | 5e7ffc56d8aa4e3d811cff62d57dc806 |
| service_name | neutron                          |
| service_type | network                          |
| url          | http://controller:9696           |
+--------------+----------------------------------+
root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   network internal http://controller:9696

+--------------+----------------------------------+
| Field        | Value                            |
+--------------+----------------------------------+
| enabled      | True                             |
| id           | adc3c41c8c584c43b2bc1b80017f987a |
| interface    | internal                         |
| region       | RegionOne                        |
| region_id    | RegionOne                        |
| service_id   | 5e7ffc56d8aa4e3d811cff62d57dc806 |
| service_name | neutron                          |
| service_type | network                          |
| url          | http://controller:9696           |
+--------------+----------------------------------+
root@controller:~# openstack endpoint create \
>   --region RegionOne \
>   network admin http://controller:9696

+--------------+----------------------------------+
| Field        | Value                            |
+--------------+----------------------------------+
| enabled      | True                             |
| id           | 2520a477809c44cca9279a9de025677f |
| interface    | admin                            |
| region       | RegionOne                        |
| region_id    | RegionOne                        |
| service_id   | 5e7ffc56d8aa4e3d811cff62d57dc806 |
| service_name | neutron                          |
| service_type | network                          |
| url          | http://controller:9696           |
+--------------+----------------------------------+

```

#### 7.2.2. Install And Configure Neutron

1、安装 Neutron：

```
$ apt install neutron-server neutron-plugin-ml2 neutron-linuxbridge-agent neutron-dhcp-agent neutron-metadata-agent

```

2、修改 `/etc/neutron/neutron.conf` 配置文件：

在 `[database]` 部分，配置数据库访问连接：

```
[database]
...
connection = mysql+pymysql://neutron:NEUTRON_DBPASS@controller/neutron

```

**注：将 `NEUTRON_DBPASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 部分，启用 Modular Layer 2 (ML2) 插件并且禁用其他插件：

```
[DEFAULT]
...
core_plugin = ml2
service_plugins =

```

在 `[DEFAULT]` 部分，配置 RabbitMQ 消息队列访问：

```
[DEFAULT]
...
transport_url = rabbit://openstack:RABBIT_PASS@controller

```

**注：将 `RABBIT_PASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 和 `[keystone_authtoken]` 部分，配置身份服务访问：

```
[DEFAULT]
...
auth_strategy = keystone

[keystone_authtoken]
...
auth_uri = http://controller:5000
auth_url = http://controller:35357
memcached_servers = controller:11211
auth_type = password
project_domain_name = Default
user_domain_name = Default
project_name = service
username = neutron
password = NEUTRON_PASS

```

**注：将 `NEUTRON_PASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 和 `[nova]` 部分，配置 Networking，将网络拓扑通知到 Compute 节点：

```
[DEFAULT]
...
notify_nova_on_port_status_changes = True
notify_nova_on_port_data_changes = True

[nova]
...
auth_url = http://controller:35357
auth_type = password
project_domain_name = Default
user_domain_name = Default
region_name = RegionOne
project_name = service
username = nova
password = NOVA_PASS

```

**注：将 `NOVA_PASS` 替换为前面设计的实际密码。**

3、配置 Modular Layer 2 (ML2) 插件：
ML2 插件使用 Linux 网桥为实例构建 L2 虚拟网络设施。

修改 `/etc/neutron/plugins/ml2/ml2_conf.ini` 配置文件：

在 `[ml2]` 部分，启用 flat、VLAN 网络：

```
[ml2]
...
type_drivers = flat,vlan

```

在 `[ml2]` 部分，禁用 self-service networks：

```
[ml2]
...
tenant_network_types =

```

在 `[ml2]` 部分，启用 Linux bridge 机制：

```
[ml2]
...
mechanism_drivers = linuxbridge

```

在 `[ml2]` 部分，启用端口安全扩展驱动：

```
[ml2]
...
extension_drivers = port_security

```

在 `[ml2_type_flat]` 部分，配置 provider 虚拟网络为 flat network：

```
[ml2_type_flat]
...
flat_networks = provider

```

在 `[securitygroup]` 部分，启用 ipset 来增强安全组规则的效率：

```
[securitygroup]
...
enable_ipset = True

```

4、配置 Linux Bridge 代理：

Linux Bridge 代理为实例构建 L2 虚拟网络设施并处理安全组。

修改 `/etc/neutron/plugins/ml2/linuxbridge_agent.ini` 配置文件：

在 `[linux_bridge]` 部分，将 provider 虚拟网络映射到 provider 物理网络接口：

```
[linux_bridge]
physical_interface_mappings = provider:PROVIDER_INTERFACE_NAME

```

**将 `PROVIDER_INTERFACE_NAME` 替换为 Controller 节点 External Network 网络接口名称 eth0**

在 `[vxlan]` 部分，禁用 VXLAN overlay 网络：

```
[vxlan]
enable_vxlan = False

```

在 `[securitygroup]` 部分，启用安全组并配置 Linux bridge iptables 防火墙：

```
[securitygroup]
...
enable_security_group = True
firewall_driver = neutron.agent.linux.iptables_firewall.IptablesFirewallDriver

```

5、配置 DHCP 代理：

修改 `/etc/neutron/dhcp_agent.ini` 配置文件：

在 `[DEFAULT]` 部分，配置 Linux Bridge Interface Driver 和 Dnsmasq DHCP Driver，启用独立的 metadata，使 provider 网络上的实例可以访问虚拟网络 metadata：

```
[DEFAULT]
...
interface_driver = neutron.agent.linux.interface.BridgeInterfaceDriver
dhcp_driver = neutron.agent.linux.dhcp.Dnsmasq
enable_isolated_metadata = True

```

6、配置 metadata 代理：

修改 `/etc/neutron/metadata_agent.ini` 配置文件：

在 `[DEFAULT]` 部分，配置 metadata host 和共享密钥：

```
[DEFAULT]
...
nova_metadata_ip = controller
metadata_proxy_shared_secret = METADATA_SECRET

```

**注：将 `METADATA_SECRET` 替换为前面设计的实际密码。**

7、为 Compute Service 配置网络访问服务：

修改 `/etc/nova/nova.conf` 配置文件：

在 `[neutron]` 部分，启用 metadata proxy 并配置共享密钥：

```
[neutron]
url = http://controller:9696
auth_url = http://controller:35357
auth_type = password
project_domain_name = Default
user_domain_name = Default
region_name = RegionOne
project_name = service
username = neutron
password = NEUTRON_PASS
service_metadata_proxy = True
metadata_proxy_shared_secret = METADATA_SECRET

```

**注：将 `NEUTRON_PASS` 和 `METADATA_SECRET` 替换为前面设计的实际密码。**

8、将配置信息写入 neutron 数据库：

```
$ su -s /bin/sh -c "neutron-db-manage --config-file /etc/neutron/neutron.conf \
  --config-file /etc/neutron/plugins/ml2/ml2_conf.ini upgrade head" neutron

```

#### 7.2.3. Finalize Installation

重启 Compute API Service：

```
$ service nova-api restart

```

重启 Networking Services：

```
$ service neutron-server restart
$ service neutron-linuxbridge-agent restart
$ service neutron-dhcp-agent restart
$ service neutron-metadata-agent restart

```

### 7.3. Install And Configure Compute Node

#### 7.3.1. Install And Configure Neutron

1、安装 Neutron：

```
$ apt install neutron-linuxbridge-agent

```

2、修改 `/etc/neutron/neutron.conf` 配置文件：

在 `[database]` 部分，注释掉任何 connection 选项，因为 Compute 节点不直接访问数据库。

在 `[DEFAULT]` 部分，配置 RabbitMQ 消息队列访问：

```
[DEFAULT]
...
transport_url = rabbit://openstack:RABBIT_PASS@controller

```

**注：将 `RABBIT_PASS` 替换为前面设计的实际密码。**

在 `[DEFAULT]` 和 `[keystone_authtoken]` 部分，配置身份服务访问：

```
[DEFAULT]
...
auth_strategy = keystone

[keystone_authtoken]
...
auth_uri = http://controller:5000
auth_url = http://controller:35357
memcached_servers = controller:11211
auth_type = password
project_domain_name = Default
user_domain_name = Default
project_name = service
username = neutron
password = NEUTRON_PASS

```

**注：将 `NEUTRON_PASS` 替换为前面设计的实际密码。**

3、配置 Linux Bridge 代理：

Linux Bridge 代理为实例构建 L2 虚拟网络设施并处理安全组。

修改 `/etc/neutron/plugins/ml2/linuxbridge_agent.ini` 配置文件：

在 `[linux_bridge]` 部分，将 provider 虚拟网络映射到 provider 物理网络接口：

```
[linux_bridge]
physical_interface_mappings = provider:PROVIDER_INTERFACE_NAME

```

**将 `PROVIDER_INTERFACE_NAME` 替换为 Compute 节点 External Network 网络接口名称 eth0**

在 `[vxlan]` 部分，禁用 VXLAN overlay 网络：

```
[vxlan]
enable_vxlan = False

```

在 `[securitygroup]` 部分，启用安全组并配置 Linux bridge iptables 防火墙：

```
[securitygroup]
...
enable_security_group = True
firewall_driver = neutron.agent.linux.iptables_firewall.IptablesFirewallDriver

```

4、为 Compute Service 配置网络访问服务：

修改 `/etc/nova/nova.conf` 配置文件：

在 `[neutron]` 部分，配置接入参数：

```
[neutron]
...
url = http://controller:9696
auth_url = http://controller:35357
auth_type = password
project_domain_name = Default
user_domain_name = Default
region_name = RegionOne
project_name = service
username = neutron
password = NEUTRON_PASS

```

#### 7.3.2. Finalize installation

重启 Compute Service：

```
$ service nova-compute restart

```

重启 Linux bridge 代理：

```
$ service neutron-linuxbridge-agent restart

```

### 7.4. Verify Operation

1、设置 OpenStack 中 Admin User 环境变量：

```
$ source ~/openstack/admin-openrc

```

2、列出已加载的扩展包，所有 neutron-server 进程启动成功：

```
root@controller:~# neutron ext-list
+---------------------------+---------------------------------+
| alias                     | name                            |
+---------------------------+---------------------------------+
| default-subnetpools       | Default Subnetpools             |
| availability_zone         | Availability Zone               |
| network_availability_zone | Network Availability Zone       |
| binding                   | Port Binding                    |
| agent                     | agent                           |
| subnet_allocation         | Subnet Allocation               |
| dhcp_agent_scheduler      | DHCP Agent Scheduler            |
| tag                       | Tag support                     |
| external-net              | Neutron external network        |
| flavors                   | Neutron Service Flavors         |
| net-mtu                   | Network MTU                     |
| network-ip-availability   | Network IP Availability         |
| quotas                    | Quota management support        |
| provider                  | Provider Network                |
| multi-provider            | Multi Provider Network          |
| address-scope             | Address scope                   |
| subnet-service-types      | Subnet service types            |
| standard-attr-timestamp   | Resource timestamps             |
| service-type              | Neutron Service Type Management |
| extra_dhcp_opt            | Neutron Extra DHCP opts         |
| standard-attr-revisions   | Resource revision numbers       |
| pagination                | Pagination support              |
| sorting                   | Sorting support                 |
| security-group            | security-group                  |
| rbac-policies             | RBAC Policies                   |
| standard-attr-description | standard-attr-description       |
| port-security             | Port Security                   |
| allowed-address-pairs     | Allowed Address Pairs           |
| project-id                | project_id field enabled        |
+---------------------------+---------------------------------+

```

## 8. Launch An Instance

### 8.1. Create Virtual Networks

1、设置 OpenStack 中 Admin User 环境变量：

```
$ source ~/openstack/admin-openrc

```

2、创建网络：

```
root@controller:~# neutron net-create \
>   --shared \
>   --provider:physical_network provider \
>   --provider:network_type flat provider

Created a new network:
+---------------------------+--------------------------------------+
| Field                     | Value                                |
+---------------------------+--------------------------------------+
| admin_state_up            | True                                 |
| availability_zone_hints   |                                      |
| availability_zones        |                                      |
| created_at                | 2018-08-13T09:36:11Z                 |
| description               |                                      |
| id                        | c9f0bdc7-72c8-469c-baae-21243e2b50d0 |
| ipv4_address_scope        |                                      |
| ipv6_address_scope        |                                      |
| mtu                       | 1500                                 |
| name                      | provider                             |
| port_security_enabled     | True                                 |
| project_id                | a0032382f4024e409f236fe922d2ee8f     |
| provider:network_type     | flat                                 |
| provider:physical_network | provider                             |
| provider:segmentation_id  |                                      |
| revision_number           | 3                                    |
| router:external           | False                                |
| shared                    | True                                 |
| status                    | ACTIVE                               |
| subnets                   |                                      |
| tags                      |                                      |
| tenant_id                 | a0032382f4024e409f236fe922d2ee8f     |
| updated_at                | 2018-08-13T09:36:11Z                 |
+---------------------------+--------------------------------------+

```

其中，`--shared` 设置允许所有项目访问该虚拟网络，`--provider:physical_network provider` 和 `--provider:network_type flat` 将扁平虚拟网络通过 Controller 节点的 eth0 连接到扁平物理网络。

3、创建子网：

```
$ openstack subnet create --network provider \
  --allocation-pool start=START_IP_ADDRESS,end=END_IP_ADDRESS \
  --dns-nameserver DNS_RESOLVER --gateway PROVIDER_NETWORK_GATEWAY \
  --subnet-range PROVIDER_NETWORK_CIDR provider

```

- `START_IP_ADDRESS` 和 `END_IP_ADDRESS` 是将分配给实例的子网的起始和结束 IP 地址，需要替换为实际起始结束 IP地址（这个IP地址范围不能包括任何已存在的活动IP）；
- `DNS_RESOLVER` 是域名服务器，需替换为实际 DNS 服务器 IP；
- `PROVIDER_NETWORK_GATEWAY` 是外部网络网关 IP，替换为实际网关 IP。

本指南使用以下命令创建子网：

```
root@controller:~# neutron subnet-create \
>   --name provider \
>   --allocation-pool start=192.168.1.100,end=192.168.1.200 \
>   --dns-nameserver 192.168.1.1 \
>   --gateway 192.168.1.1 provider 192.168.1.0/24

Created a new subnet:
+-------------------+----------------------------------------------------+
| Field             | Value                                              |
+-------------------+----------------------------------------------------+
| allocation_pools  | {"start": "192.168.1.100", "end": "192.168.1.200"} |
| cidr              | 192.168.1.0/24                                     |
| created_at        | 2018-08-13T09:45:08Z                               |
| description       |                                                    |
| dns_nameservers   | 192.168.1.1                                        |
| enable_dhcp       | True                                               |
| gateway_ip        | 192.168.1.1                                        |
| host_routes       |                                                    |
| id                | 3c96c886-aec3-4104-bfe8-7497228a442d               |
| ip_version        | 4                                                  |
| ipv6_address_mode |                                                    |
| ipv6_ra_mode      |                                                    |
| name              | provider                                           |
| network_id        | c9f0bdc7-72c8-469c-baae-21243e2b50d0               |
| project_id        | a0032382f4024e409f236fe922d2ee8f                   |
| revision_number   | 2                                                  |
| service_types     |                                                    |
| subnetpool_id     |                                                    |
| tenant_id         | a0032382f4024e409f236fe922d2ee8f                   |
| updated_at        | 2018-08-13T09:45:08Z                               |
+-------------------+----------------------------------------------------+

```

### 8.2. Create Flavor

为 CirrOS 镜像创建用于测试的虚拟机类型模板 m1.nano：

```
root@controller:~# openstack flavor create --id 0 --vcpus 1 --ram 64 --disk 1 m1.nano

+----------------------------+---------+
| Field                      | Value   |
+----------------------------+---------+
| OS-FLV-DISABLED:disabled   | False   |
| OS-FLV-EXT-DATA:ephemeral  | 0       |
| disk                       | 1       |
| id                         | 0       |
| name                       | m1.nano |
| os-flavor-access:is_public | True    |
| properties                 |         |
| ram                        | 64      |
| rxtx_factor                | 1.0     |
| swap                       |         |
| vcpus                      | 1       |
+----------------------------+---------+

```

### 8.3. Generate A Key Pair

大多数云平台镜像支持公钥认证而不支持传统的口令认证，在启动实例前必须添加一个公钥。生成密钥对命令如下：

```
root@controller:~# ssh-keygen -q -N ""
Enter file in which to save the key (/root/.ssh/id_rsa):

root@controller:~# openstack keypair create --public-key ~/.ssh/id_rsa.pub mykey
+-------------+-------------------------------------------------+
| Field       | Value                                           |
+-------------+-------------------------------------------------+
| fingerprint | fe:f1:ba:2d:35:14:ff:06:eb:3a:b2:b5:06:25:4d:1f |
| name        | mykey                                           |
| user_id     | 3082e8e6887e47ccac419b9b2b9336f5                |
+-------------+-------------------------------------------------+

```

### 8.4. Add Security Group Rules

默认安全组规则适用于所有实例，并且包含防火墙规则，该防火墙规则拒绝远程访问实例。对于 Linux 镜像，建议至少允许 ICMP 和 SSH。添加规则到默认安全组命令如下：

```
root@controller:~# openstack security group rule create --proto icmp default

+-------------------+--------------------------------------+
| Field             | Value                                |
+-------------------+--------------------------------------+
| created_at        | 2018-08-14T02:07:37Z                 |
| description       |                                      |
| direction         | ingress                              |
| ethertype         | IPv4                                 |
| headers           |                                      |
| id                | 84c3c4e3-4f79-4c6e-b969-01f0c9a0b79c |
| port_range_max    | None                                 |
| port_range_min    | None                                 |
| project_id        | a0032382f4024e409f236fe922d2ee8f     |
| project_id        | a0032382f4024e409f236fe922d2ee8f     |
| protocol          | icmp                                 |
| remote_group_id   | None                                 |
| remote_ip_prefix  | 0.0.0.0/0                            |
| revision_number   | 1                                    |
| security_group_id | bd4bc795-4012-445f-80c7-ccfe542e4ed0 |
| updated_at        | 2018-08-14T02:07:37Z                 |
+-------------------+--------------------------------------+
root@controller:~# openstack security group rule create --proto tcp --dst-port 22 default

+-------------------+--------------------------------------+
| Field             | Value                                |
+-------------------+--------------------------------------+
| created_at        | 2018-08-14T02:10:44Z                 |
| description       |                                      |
| direction         | ingress                              |
| ethertype         | IPv4                                 |
| headers           |                                      |
| id                | ad257bb7-0967-460d-8ac6-a2df25c1243d |
| port_range_max    | 22                                   |
| port_range_min    | 22                                   |
| project_id        | a0032382f4024e409f236fe922d2ee8f     |
| project_id        | a0032382f4024e409f236fe922d2ee8f     |
| protocol          | tcp                                  |
| remote_group_id   | None                                 |
| remote_ip_prefix  | 0.0.0.0/0                            |
| revision_number   | 1                                    |
| security_group_id | bd4bc795-4012-445f-80c7-ccfe542e4ed0 |
| updated_at        | 2018-08-14T02:10:44Z                 |
+-------------------+--------------------------------------+

```

### 8.5. Launch An Instance On The Provider Network

启动实例前，至少需要制定虚拟机模板类型、镜像名称、网络、安全组、密钥对和实例名称。

```
$ openstack server create --flavor m1.nano --image cirros \
  --nic net-id=PROVIDER_NET_ID --security-group default \
  --key-name mykey provider-instance

```

**注：将 PROVIDER_NET_ID 替换为实际 Provider 网络 ID。**

本指南使用以下命令创建实例：

```
root@controller:~# openstack server create \
>   --flavor m1.nano \
>   --image cirros \
>   --nic net-id=c9f0bdc7-72c8-469c-baae-21243e2b50d0 \
>   --security-group default \
>   --key-name mykey \
>   demo1
+--------------------------------------+-----------------------------------------------+
| Field                                | Value                                         |
+--------------------------------------+-----------------------------------------------+
| OS-DCF:diskConfig                    | MANUAL                                        |
| OS-EXT-AZ:availability_zone          |                                               |
| OS-EXT-SRV-ATTR:host                 | None                                          |
| OS-EXT-SRV-ATTR:hypervisor_hostname  | None                                          |
| OS-EXT-SRV-ATTR:instance_name        |                                               |
| OS-EXT-STS:power_state               | NOSTATE                                       |
| OS-EXT-STS:task_state                | scheduling                                    |
| OS-EXT-STS:vm_state                  | building                                      |
| OS-SRV-USG:launched_at               | None                                          |
| OS-SRV-USG:terminated_at             | None                                          |
| accessIPv4                           |                                               |
| accessIPv6                           |                                               |
| addresses                            |                                               |
| adminPass                            | ZNVosJh9GWjz                                  |
| config_drive                         |                                               |
| created                              | 2018-08-14T03:08:24Z                          |
| flavor                               | m1.nano (0)                                   |
| hostId                               |                                               |
| id                                   | 0c674ad8-fa7d-4a07-b540-fc83213dd528          |
| image                                | cirros (54cd1762-36c9-4ecd-b3ac-793a19c6f796) |
| key_name                             | mykey                                         |
| name                                 | demo1                                         |
| os-extended-volumes:volumes_attached | []                                            |
| progress                             | 0                                             |
| project_id                           | a0032382f4024e409f236fe922d2ee8f              |
| properties                           |                                               |
| security_groups                      | [{u'name': u'default'}]                       |
| status                               | BUILD                                         |
| updated                              | 2018-08-14T03:08:24Z                          |
| user_id                              | 3082e8e6887e47ccac419b9b2b9336f5              |
+--------------------------------------+-----------------------------------------------+

```

检查实例状态：

```
root@controller:~# openstack server list

+--------------------------------------+-------+--------+------------------------+------------+
| ID                                   | Name  | Status | Networks               | Image Name |
+--------------------------------------+-------+--------+------------------------+------------+
| 0c674ad8-fa7d-4a07-b540-fc83213dd528 | demo1 | ACTIVE | provider=192.168.1.108 | cirros     |
+--------------------------------------+-------+--------+------------------------+------------+

```

使用虚拟控制台访问实例：

获取一个 Virtual Network Computing (VNC) 会话 URL，通过浏览器访问：

```
root@controller:~# openstack console url show demo1

+-------+---------------------------------------------------------------------------------+
| Field | Value                                                                           |
+-------+---------------------------------------------------------------------------------+
| type  | novnc                                                                           |
| url   | http://controller:6080/vnc_auto.html?token=24495335-10b9-480e-a784-42d929146a34 |
+-------+---------------------------------------------------------------------------------+

```

**注：将 url 中的 `controller` 替换为 Controller Node 的 Management Network IP 地址。**

由于公司网络限制，无法访问改地址。

测试能否 ping 通实例：

```
root@controller:~# ping -c 4 192.168.1.108
PING 192.168.1.108 (192.168.1.108) 56(84) bytes of data.
64 bytes from 192.168.1.108: icmp_seq=1 ttl=64 time=7.61 ms
64 bytes from 192.168.1.108: icmp_seq=2 ttl=64 time=1.74 ms
64 bytes from 192.168.1.108: icmp_seq=3 ttl=64 time=1.87 ms
64 bytes from 192.168.1.108: icmp_seq=4 ttl=64 time=2.55 ms

--- 192.168.1.108 ping statistics ---
4 packets transmitted, 4 received, 0% packet loss, time 3004ms
rtt min/avg/max/mdev = 1.748/3.450/7.619/2.426 ms

```

远程访问实例：

```
root@controller:~# ssh cirros@192.168.1.108
The authenticity of host '192.168.1.108 (192.168.1.108)' can't be established.
RSA key fingerprint is SHA256:MYYYooajUE9bl+qHZpEoLPTtX+Mw1+Blifv5sgSJ+UE.
Are you sure you want to continue connecting (yes/no)? yes
Warning: Permanently added '192.168.1.108' (RSA) to the list of known hosts.
cirros@192.168.1.108's password:
$ ping 192.168.1.11
PING 192.168.1.11 (192.168.1.11): 56 data bytes
64 bytes from 192.168.1.11: seq=0 ttl=64 time=14.331 ms
64 bytes from 192.168.1.11: seq=1 ttl=64 time=1.449 ms
64 bytes from 192.168.1.11: seq=2 ttl=64 time=1.337 ms
64 bytes from 192.168.1.11: seq=3 ttl=64 time=3.350 ms
^C
--- 192.168.1.11 ping statistics ---
4 packets transmitted, 4 packets received, 0% packet loss
round-trip min/avg/max = 1.337/5.116/14.331 ms

```

**注：cirros 镜像的默认密码是 cubswin:)**

## 9. Questions & Answers

### 9.1. Create Project Failed

创建 Project 时失败，log 如下：

<img decoding="async" src="http://static.zybuluo.com/Maxwelli/lwdt83hjuolxclm5shqz3oho/20180809165111.jpg" alt="20180809165111.jpg">

Keystone 无法连接到网关，考虑到之前配置了 http_proxy，可能会有影响，取消掉之后鉴权成功。

<img decoding="async" src="http://static.zybuluo.com/Maxwelli/lnq57mpqt7c0gvehqskscomj/20180809171420.jpg" alt="20180809171420.jpg">

虚拟机取消 http_proxy 之后就无法访问外网。所以期望能单独在 eth0 上面配置代理，但是没有找到配置方案。考虑到虚拟机只有安装包的时候需要访问外网，所以直接给 apt 配置代理。

<img decoding="async" src="http://static.zybuluo.com/Maxwelli/utstrvp7qoeneiscyined91s/20180809174455.jpg" alt="20180809174455.jpg">

### 9.2 Populate Neutron database Failed

修改完 Neutron 的配置文件，同步数据库时，出现以下错误：

```
root@controller:~# su -s /bin/sh -c "neutron-db-manage --config-file /etc/neutron/neutron.conf \
  --config-file /etc/neutron/plugins/ml2/ml2_conf.ini upgrade head" neutron
INFO  [alembic.runtime.migration] Context impl SQLiteImpl.
INFO  [alembic.runtime.migration] Will assume non-transactional DDL.
  Running upgrade for neutron ...
INFO  [alembic.runtime.migration] Context impl SQLiteImpl.
INFO  [alembic.runtime.migration] Will assume non-transactional DDL.
INFO  [alembic.runtime.migration] Running upgrade  -> kilo, kilo_initial
Traceback (most recent call last):
  File "/usr/bin/neutron-db-manage", line 10, in <module>
    sys.exit(main())
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/cli.py", line 686, in main
    return_val |= bool(CONF.command.func(config, CONF.command.name))
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/cli.py", line 207, in do_upgrade
    desc=branch, sql=CONF.command.sql)
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/cli.py", line 108, in do_alembic_command
    getattr(alembic_command, cmd)(config, *args, **kwargs)
  File "/usr/lib/python2.7/dist-packages/alembic/command.py", line 174, in upgrade
    script.run_env()
  File "/usr/lib/python2.7/dist-packages/alembic/script/base.py", line 407, in run_env
    util.load_python_file(self.dir, 'env.py')
  File "/usr/lib/python2.7/dist-packages/alembic/util/pyfiles.py", line 93, in load_python_file
    module = load_module_py(module_id, path)
  File "/usr/lib/python2.7/dist-packages/alembic/util/compat.py", line 79, in load_module_py
    mod = imp.load_source(module_id, path, fp)
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/alembic_migrations/env.py", line 120, in <module>
    run_migrations_online()
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/alembic_migrations/env.py", line 114, in run_migrations_online
    context.run_migrations()
  File "<string>", line 8, in run_migrations
  File "/usr/lib/python2.7/dist-packages/alembic/runtime/environment.py", line 797, in run_migrations
    self.get_context().run_migrations(**kw)
  File "/usr/lib/python2.7/dist-packages/alembic/runtime/migration.py", line 312, in run_migrations
    step.migration_fn(**kw)
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/alembic_migrations/versions/kilo_initial.py", line 53, in upgrade
    migration.pk_on_alembic_version_table()
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/__init__.py", line 220, in pk_on_alembic_version_table
    'alembic_version', ['version_num'])
  File "<string>", line 8, in create_primary_key
  File "<string>", line 3, in create_primary_key
  File "/usr/lib/python2.7/dist-packages/alembic/operations/ops.py", line 265, in create_primary_key
    return operations.invoke(op)
  File "/usr/lib/python2.7/dist-packages/alembic/operations/base.py", line 318, in invoke
    return fn(self, operation)
  File "/usr/lib/python2.7/dist-packages/alembic/operations/toimpl.py", line 135, in create_constraint
    operation.to_constraint(operations.migration_context)
  File "/usr/lib/python2.7/dist-packages/alembic/ddl/sqlite.py", line 34, in add_constraint
    "No support for ALTER of constraints in SQLite dialect")
NotImplementedError: No support for ALTER of constraints in SQLite dialect

```

查找万能谷歌，发现在 OpenStack Q&A 上有人碰到过同样的问题：[SQL error during alembic.migration when populating Neutron database on MariaDB 10.0](https://ask.openstack.org/en/question/61089/sql-error-during-alembicmigration-when-populating-neutron-database-on-mariadb-100/)。下面的回答中讲到，如果有多条 connection，会出现该问题。检查 neutron.conf 文件中的 `[DEFAULT]` 部分，只有一条 connection。考虑到这是连接数据库的内容，在 `[database]` 部分中再次确认，果然有一条多余的 connection：

```
[database]
...
connection = sqlite:////var/lib/neutron/neutron.sqlite

```

将该条 connection 注释掉，重新执行同步命令，出现以下错误：

```
root@controller:~# su -s /bin/sh -c "neutron-db-manage --config-file /etc/neutron/neutron.conf \
  --config-file /etc/neutron/plugins/ml2/ml2_conf.ini upgrade head" neutron
Traceback (most recent call last):
  File "/usr/bin/neutron-db-manage", line 10, in <module>
    sys.exit(main())
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/cli.py", line 686, in main
    return_val |= bool(CONF.command.func(config, CONF.command.name))
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/cli.py", line 205, in do_upgrade
    run_sanity_checks(config, revision)
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/cli.py", line 670, in run_sanity_checks
    script_dir.run_env()
  File "/usr/lib/python2.7/dist-packages/alembic/script/base.py", line 407, in run_env
    util.load_python_file(self.dir, 'env.py')
  File "/usr/lib/python2.7/dist-packages/alembic/util/pyfiles.py", line 93, in load_python_file
    module = load_module_py(module_id, path)
  File "/usr/lib/python2.7/dist-packages/alembic/util/compat.py", line 79, in load_module_py
    mod = imp.load_source(module_id, path, fp)
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/alembic_migrations/env.py", line 120, in <module>
    run_migrations_online()
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/alembic_migrations/env.py", line 106, in run_migrations_online
    with DBConnection(neutron_config.database.connection, connection) as conn:
  File "/usr/lib/python2.7/dist-packages/neutron/db/migration/connection.py", line 32, in __enter__
    self.engine = session.create_engine(self.connection_url)
  File "/usr/lib/python2.7/dist-packages/oslo_db/sqlalchemy/engines.py", line 114, in create_engine
    url = sqlalchemy.engine.url.make_url(sql_connection)
  File "/usr/lib/python2.7/dist-packages/sqlalchemy/engine/url.py", line 186, in make_url
    return _parse_rfc1738_args(name_or_url)
  File "/usr/lib/python2.7/dist-packages/sqlalchemy/engine/url.py", line 235, in _parse_rfc1738_args
    "Could not parse rfc1738 URL from string '%s'" % name)
sqlalchemy.exc.ArgumentError: Could not parse rfc1738 URL from string ''

```

在 `[database]` 部分，关于 connection 有以下描述：

```
# The SQLAlchemy connection string to use to connect to the database. (string
# value)
# Deprecated group/name - [DEFAULT]/sql_connection
# Deprecated group/name - [DATABASE]/sql_connection
# Deprecated group/name - [sql]/connection
#connection = sqlite:////var/lib/neutron/neutron.sqlite

```

connection 配置项应该放在 `[database]` 部分，如果要放在 `[DEFAULT]` 部分，应该使用 sql_connection。修改后，同步数据库成功。

## Appendix

[OpenStack Installation Tutorial for Ubuntu](https://docs.openstack.org/newton/install-guide-ubuntu/)
[Keystone WIKI](https://wiki.openstack.org/wiki/Keystone)
[Keystone’s Token](https://www.zybuluo.com/Maxwelli/note/1251287)
[Glance WIKI](https://wiki.openstack.org/wiki/Glance)
[Disk and container formats for images](https://docs.openstack.org/image-guide/image-formats.html)。
[Nova WIKI](https://wiki.openstack.org/wiki/Nova)
[Neutron WIKI](https://wiki.openstack.org/wiki/Neutron)

</div>
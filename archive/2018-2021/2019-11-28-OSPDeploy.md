---
title: "OSP Deploy"
date: 2019-11-28T16:41:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [23, 27]
url: http://47.84.100.47/?p=254
---


<pre class="wp-block-syntaxhighlighter-code">#### 配置 UnderCloud IP
ip addr add 10.57.199.246/24 dev enp0s3
ip route add default via 10.57.199.1

#### 配置安装源
# Upload osp10.repo & redhat.repo
rm -rf /etc/yum.repos.d/*
scp root@10.57.217.10:/root/osp10.repo /etc/yum.repos.d/
yum update -y

#### 创建 Stack 用户
useradd stack
passwd stack
echo "stack ALL=(root) NOPASSWD:ALL" | tee -a /etc/sudoers.d/stack
chmod 0440 /etc/sudoers.d/stack
su - stack

#### 修改主机名
hostname
hostname -f
sudo hostnamectl set-hostname blue.localhost
sudo hostnamectl set-hostname --transient blue.localhost

#### 创建镜像目录
mkdir ~/images
mkdir ~/templates-blue-osp10

#### 安装 Director 包
sudo yum install -y python-tripleoclient

#### 配置安装 Director
cp /usr/share/instack-undercloud/undercloud.conf.sample ~/undercloud.conf
openstack undercloud install
source stackrc

#### 获取 Overcloud 节点镜像
sudo yum install -y rhosp-director-images rhosp-director-images-ipa
cd ~/images
for i in /usr/share/rhosp-director-images/overcloud-full-latest-10.0.tar /usr/share/rhosp-director-images/ironic-python-agent-latest-10.0.tar; do tar -xvf $i; done
openstack overcloud image upload --image-path /home/stack/images/
cd ..

#### 设置子网域名解析器
openstack subnet set --dns-nameserver 10.56.126.31 $(openstack subnet list -c ID -f value)

#### 注册 Overcloud 节点
# Edit instackenv.json
openstack baremetal import --json ~/instackenv.json
openstack baremetal configure boot
openstack baremetal node list

#### 检查节点硬件
for node in $(openstack baremetal node list -c UUID -f value); do openstack baremetal node manage $node; done
date; time openstack overcloud node introspect --all-manageable --provide; date

sudo journalctl -l -u openstack-ironic-inspector -u openstack-ironicinspector-dnsmasq -u openstack-ironic-conductor -f

#### 添加节点标签
openstack baremetal node set --property capabilities='profile:control,boot_option:local' compute5
openstack baremetal node set --property capabilities='profile:compute,boot_option:local' compute14

#### 配置 Overcloud
# Upload first-boot.yaml & network-environment.yaml & nic-configs & node-info.yaml & post-install.yaml

#### 创建 Overcloud
openstack overcloud deploy --templates \
    -e /home/stack/templates-blue-osp10/node-info.yaml \
    -e /home/stack/templates-blue-osp10/network-environment.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-isolation.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/neutron-sriov.yaml \
    --log-file overcloud_install.log

openstack stack delete overcloud --yes --wait

openstack quota set --cores -1 --fixed-ips -1 --injected-files -1 --injected-file-size -1 --injected-path-size -1 --instances -1 --key-pairs -1 --ram -1 --server-groups -1 --server-group-members -1 admin
openstack quota set --properties -1 --gigabytes -1 --snapshots -1 --volumes -1 admin
openstack quota set --floating-ips -1 --networks -1 --ports -1 --rbac-policies -1 --routers -1 --secgroups -1 --secgroup-rules -1 --subnets -1 --subnetpools -1 admin

nova aggregate-create nova1 nova1
nova aggregate-add-host nova1 compute-1.localdomain
nova aggregate-create nova2 nova2
nova aggregate-add-host nova2 compute-2.localdomain
nova aggregate-create nova3 nova3
nova aggregate-add-host nova3 compute-3.localdomain
nova availability-zone-list
</pre>
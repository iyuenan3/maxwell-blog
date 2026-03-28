---
title: "OSP Deploy"
date: 2019-12-28T16:43:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [23, 27]
url: http://47.84.100.47/?p=257
---


<pre class="wp-block-syntaxhighlighter-code">#### 创建 Undercloud 虚拟机
cp rhel7.6.qcow2 undercloud-5.qcow2
virsh define undercloud-5.xml
virsh start undercloud-5
virsh console undercloud-5
xfs_growfs /
dd if=/dev/zero of=test bs=1M count=10000

#### 配置临时 IP
ip addr add 10.57.199.246/24 dev enp0s3
ip route add default via 10.57.199.1

#### 配置 UnderCloud IP
cat >> /etc/sysconfig/network-scripts/ifcfg-enp0s3 << EOF

TYPE=Ethernet
BOOTPROTO=static
IPV4_FAILURE_FATAL=no
IPV6INIT=yes
IPV6_AUTOCONF=yes
IPV6_DEFROUTE=yes
IPV6_PEERDNS=yes
IPV6_PEERROUTES=yes
IPV6_FAILURE_FATAL=no
IPV6_ADDR_GEN_MODE=stable-privacy
NAME=enp0s3
UUID=7b253d4e-58f6-47e6-ab42-ec6133406330
DEVICE=enp0s3
ONBOOT=yes
IPADDR=10.57.199.246
NETMASK=255.255.255.224
GATEWAY=10.57.199.225
DNS=10.56.126.31
EOF
systemctl restart network

#### 修改主机名
hostname
sudo hostnamectl set-hostname blue.localhost

#### 配置 proxy
vi /etc/rhsm/rhsm.conf
proxy_hostname = 10.144.1.10
proxy_port = 8080

cat >> /etc/yum.conf << EOF
proxy=http://10.144.1.10:8080/
proxy=https://10.144.1.10:8080/
EOF

#### 创建 Stack 用户
useradd stack
passwd stack
echo "stack ALL=(root) NOPASSWD:ALL" | tee -a /etc/sudoers.d/stack
chmod 0440 /etc/sudoers.d/stack
su - stack

#### 注册主机
sudo subscription-manager register --username=nokia-cloudran-osp --password=nokiacloudran
sudo subscription-manager list --available --all --matches="Red Hat OpenStack"
sudo subscription-manager attach --pool=123456
sudo subscription-manager repos --disable=*
sudo subscription-manager repos --enable=rhel-7-server-rpms --enable=rhel-7-server-extras-rpms --enable=rhel-7-server-rh-common-rpms --enable=rhel-ha-for-rhel-7-server-rpms --enable=rhel-7-server-openstack-13-rpms

#### 更新系统上的软件
sudo yum update -y
sudo reboot

#### Undercloud Deploy
sudo yum install -y wget vim screen crudini python-tripleoclient
scp root@10.57.199.254:/root/Maxwell_Li/OSP13/undercloud.conf ./
openstack undercloud install

#### Get Images
source ~/stackrc
sudo yum install -y rhosp-director-images rhosp-director-images-ipa
mkdir images
cd images/
for i in /usr/share/rhosp-director-images/overcloud-full-latest-13.0.tar /usr/share/rhosp-director-images/ironic-python-agent-latest-13.0.tar; do tar -xvf $i;done
openstack overcloud image upload --image-path ~/images/
openstack image list
cd ..

openstack subnet set ctlplane-subnet --dns-nameserver 10.56.126.31

sudo cat >> /etc/systemd/system/docker.service.d/99-unset-mountflags.conf << EOF
Environment="HTTP_PROXY=http://10.144.1.10:8080"
Environment="HTTPS_PROXY=http://10.144.1.10:8080"
Environment="NO_PROXY=localhost,127.0.0.1,192.168.24.1"
EOF
sudo systemctl daemon-reload
sudo systemctl restart docker
sudo systemctl show --property=Environment docker

mkdir templates-blue-osp13
touch templates-blue-osp13/overcloud_images.yaml
touch local_registry_images.yaml

export http_proxy=http://10.144.1.10:8080/
export no_proxy="blue,localhost,127.0.0.1,192.168.24.1"

openstack overcloud container image prepare \
    --namespace=registry.access.redhat.com/rhosp13 \
    --push-destination=192.168.24.1:8787 \
    --prefix=openstack- \
    --tag-from-label {version}-{release} \
    --output-env-file=/home/stack/templates-blue-osp13/overcloud_images.yaml \
    --output-images-file /home/stack/local_registry_images.yaml

unset http_proxy
unset no_proxy

cat >> local_registry_images.yaml << EOF
- imagename: registry.access.redhat.com/rhosp13/openstack-neutron-sriov-agent:latest
  push_destination: 192.168.24.1:8787
EOF
cat >> templates-blue-osp13/overcloud_images.yaml << EOF
  DockerNeutronSriovImage: 192.168.24.1:8787/rhosp13/openstack-neutron-sriov-agent:latest
EOF

sudo openstack overcloud container image upload \
     --config-file /home/stack/local_registry_images.yaml \
     --verbose

#docker pull registry.access.redhat.com/rhosp13/openstack-neutron-sriov-agent:latest

scp root@10.57.199.254:/root/Maxwell_Li/OSP13/instackenv.json ./
openstack overcloud node import ~/instackenv.json
openstack baremetal node list

/etc/ironic-inspector/inspector.conf  timeout

for node in $(openstack baremetal node list --fields uuid -f value) ; do openstack baremetal node manage $node ; done
date; time openstack overcloud node introspect [NODE UUID] --provide; date
date; time openstack overcloud node introspect --all-manageable --provide; date
sudo journalctl -l -u openstack-ironic-inspector -u openstack-ironic-inspector-dnsmasq -u openstack-ironic-conductor -f

openstack baremetal node set --property capabilities='profile:compute,boot_option:local' 66e67a20-61fc-43f4-b7ce-f03a74e217e1
openstack baremetal node set --property capabilities='profile:control,boot_option:local' 77cc24b1-9432-4729-9e66-0daf87c9aaaa

############ OSP13 Deploy
scp root@10.57.199.254:/root/Maxwell_Li/OSP13/templates/* ./templates/
sudo scp -r root@10.57.199.254:/root/Maxwell_Li/OSP13/custom-bond-nics /usr/share/openstack-tripleo-heat-templates/network/config/
openstack overcloud roles generate -o roles_data.yaml Controller ComputeSriov

openstack overcloud delete overcloud --yes

openstack overcloud deploy --templates \
    -r /home/stack/templates-blue-osp13/roles_data.yaml \
    -e /home/stack/templates-blue-osp13/node-info.yaml \
    -e /home/stack/templates-blue-osp13/overcloud_images.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/ovs-hw-offload.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/host-config-and-reboot.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/neutron-sriov.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-environment.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-isolation.yaml \
    --log-file overcloud_install.log

openstack quota set --cores -1 --fixed-ips -1 --injected-files -1 --injected-file-size -1 --injected-path-size -1 --instances -1 --key-pairs -1 --ram -1 --server-groups -1 --server-group-members -1 admin
openstack quota set --backups -1 --gigabytes -1 --backup-gigabytes -1 --per-volume-gigabytes -1 --snapshots -1 --volumes -1 admin
openstack quota set --floating-ips -1 --networks -1 --ports -1 --rbac-policies -1 --routers -1 --secgroups -1 --secgroup-rules -1 --subnets -1 --subnetpools -1 admin</pre>
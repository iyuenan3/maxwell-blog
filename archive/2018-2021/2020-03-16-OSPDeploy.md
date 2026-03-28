---
title: "OSP. Deploy"
date: 2020-03-16T16:44:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [23, 27]
url: http://47.84.100.47/?p=260
---


<pre class="wp-block-syntaxhighlighter-code">## Download rhel 8.1 image.
在 https://access.redhat.com/downloads/content/479/ver=/rhel---8/8.1/x86_64/product-software 网站下载 ISO
计算sha256校验码：
certutil -hashfile rhel-8.1-x86_64-kvm.qcow2 SHA256

制作 KVM image
qemu-img create -f qcow2 rhel8.1_osp16.qcow2 200G

root@undercloud[21:27:08]:/home/undercloud# virt-df -h rhel-8.1-x86_64-kvm.qcow2
Filesystem                                Size       Used  Available  Use%
rhel8.1_osp16.qcow2:/dev/sda1             7.8G       1.1G       6.7G   15%
root@undercloud[21:28:15]:/home/undercloud# virt-resize --expand /dev/sda1 rhel-8.1-x86_64-kvm.qcow2 rhel8.1_osp16.qcow2
[   0.0] Examining rhel-8.1-x86_64-kvm.qcow2
**********

Summary of changes:

virt-resize: warning: unknown/unavailable method for expanding the xfs 
filesystem on /dev/sda1
/dev/sda1: This partition will be resized from 7.8G to 200.0G.

**********
[   4.0] Setting up initial partition table on rhel8.1.qcow2
[   4.2] Copying /dev/sda1
 100% ⟦▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒⟧ 00:00

Resize operation completed with no errors.  Before deleting the old disk, 
carefully check that the resized disk boots and works correctly.

curl -O http://download.libguestfs.org/binaries/appliance/appliance-1.40.1.tar.xz
tar xvfJ appliance-1.40.1.tar.xz -C $HOME/
export LIBGUESTFS_PATH=$HOME/appliance/

virt-customize -a rhel8.1_osp16.qcow2 --root-password password:nokia123 --uninstall cloud-init

启动虚拟机
cp rhel8.1_osp16.qcow2 undercloud-red.qcow2
virsh define undercloud-red.xml
virsh start undercloud-red
virsh console undercloud-red

xfs_growfs /

配置 ip 和 ssh 连接
ip addr add 10.57.217.25/26 dev eth0
ip route add default via 10.57.217.1

cat > /etc/sysconfig/network-scripts/ifcfg-eth0 << EOF
DEVICE="eth0"
BOOTPROTO="static"
BOOTPROTOv6="dhcp"
ONBOOT="yes"
TYPE="Ethernet"
IPADDR=10.57.217.25
NETMASK=255.255.255.192
GATEWAY=10.57.217.1
DNS=10.56.126.31
EOF</pre>

<pre class="wp-block-syntaxhighlighter-code">################################## Check List Before Deploy ######################################
# 1、所有被装节点，iOL 设置 IPMI over LAN Access Enable。
# 2、hostname 用小写，no_proxy 里面要加入 localdomain。
# 3、ctlplane 网络 dhcp 地址要大于被装节点数量。
# 4、controller 节点所有硬盘组一个 raid0 或 raid1，总容量大于 1TB。CinderLVMLoopDeviceSize 要小于总容量。
# 5、network.yaml 文件里面的 5个网络与 controller 和 compute 配置文件要一致。检查 interface 数量。
# 6、部署 overcloud 之前执行 image prepare，失败了的话多跑几遍，pull image 比较慢。
###################################################################################################</pre>

<pre class="wp-block-syntaxhighlighter-code">#### 修改主机名
hostname
sudo hostnamectl set-hostname pink.localhost

#### 配置 proxy
cat > /etc/environment << EOF
http_proxy=http://10.158.100.1:8080/
https_proxy=http://10.158.100.1:8080/
no_proxy=pink,pink.localhost,localhost,pink.ctlplane,pink.ctlplane.localdomain,127.0.0.1,192.168.28.0/24,10.107.196.126
EOF

cat > /etc/resolv.conf << EOF
nameserver 10.56.126.31
EOF

#### 创建 Stack 用户
useradd stack
passwd stack
echo "stack ALL=(root) NOPASSWD:ALL" | tee -a /etc/sudoers.d/stack
chmod 0440 /etc/sudoers.d/stack
su - stack

mkdir ~/images
mkdir ~/templates

#### 注册主机
sudo subscription-manager register --username=nokia-cloudran-osp --password=nokiacloudran
sudo subscription-manager list --available --all --matches="Red Hat OpenStack"
sudo subscription-manager attach --pool=8a85f99c707807c80170a0c650dd3ba8
sudo subscription-manager release --set=8.2
sudo subscription-manager repos --disable=*
sudo subscription-manager repos --enable=rhel-8-for-x86_64-baseos-eus-rpms --enable=rhel-8-for-x86_64-appstream-eus-rpms --enable=rhel-8-for-x86_64-highavailability-eus-rpms --enable=ansible-2.9-for-rhel-8-x86_64-rpms --enable=openstack-16.1-for-rhel-8-x86_64-rpms --enable=fast-datapath-for-rhel-8-x86_64-rpms
sudo dnf module disable -y container-tools:rhel8
sudo dnf module enable -y container-tools:2.0

#### 更新系统上的软件
sudo dnf update -y
sudo reboot

#### Undercloud Deploy
sudo dnf install -y python3-tripleoclient wget vim

# config containers-prepare-parameter.yaml
openstack tripleo container image prepare default \
    --local-push-destination \
    --output-env-file containers-prepare-parameter.yaml

cat >> containers-prepare-parameter.yaml << EOF
  ContainerImageRegistryCredentials:
    registry.redhat.io:
      nokia-cloudran-osp: nokiacloudran
  ContainerImageRegistryLogin: true
EOF

cp templates/containers-prepare-parameter.yaml ./

# config undercloud.conf
cp templates/undercloud.conf ./
openstack undercloud install

# Obtaining images for overcloud nodes
source ~/stackrc

# Install the rhosp-director-images and rhosp-director-images-ipa packages:
sudo dnf install -y rhosp-director-images rhosp-director-images-ipa

# Extract the images archives to the images directory in the stack user’s home (/home/stack/images):
cd ~/images
for i in /usr/share/rhosp-director-images/overcloud-full-latest-16.1.tar /usr/share/rhosp-director-images/ironic-python-agent-latest-16.1.tar; do tar -xvf $i; done
openstack overcloud image upload --image-path /home/stack/images/
cd ..

openstack image list
ls -l /var/lib/ironic/httpboot

# Setting a nameserver for the control plane
openstack subnet set --dns-nameserver 10.56.126.31 ctlplane-subnet

# Undercloud container registry
sudo systemctl restart httpd

# Registering nodes for the overcloud
cp templates/instackenv.json ./
openstack overcloud node import --validate-only ~/instackenv.json
openstack overcloud node import ~/instackenv.json
openstack baremetal node list

# Inspecting the hardware of nodes
time openstack overcloud node introspect --all-manageable --provide

# Tagging nodes into profiles
for node in $(openstack baremetal node list -c UUID -c Name -f value | grep DL360 | awk '{print $1}'); do openstack baremetal node set --property capabilities='profile:control,boot_option:local' $node ; done
for node in $(openstack baremetal node list -c UUID -c Name -f value | grep C7000 | awk '{print $1}'); do openstack baremetal node set --property capabilities='profile:compute,boot_option:local' $node ; done
openstack overcloud profiles list

# Deployment
openstack overcloud roles generate -o roles_data.yaml Controller ComputeSriov

############################################################################################################################################################################################
# Modify ansible playbook
sudo su
# add proxy for podman login
cp /usr/share/ansible/roles/tripleo-podman/tasks/tripleo_podman_login.yml /usr/share/ansible/roles/tripleo-podman/tasks/tripleo_podman_login.yml_BAK
cat > /usr/share/ansible/roles/tripleo-podman/tasks/tripleo_podman_login.yml << EOF
---

- name: Perform container registry login(s)
  become: true
  shell: |-
    export http_proxy=http://10.158.100.1:8080/ && \\
    export https_proxy=http://10.158.100.1:8080/ && \\
    podman login --username=\$REGISTRY_USERNAME \\
                 --password=\$REGISTRY_PASSWORD \\
                 --tls-verify={{ tripleo_podman_tls_verify }} \\
                 \$REGISTRY
  environment:
    REGISTRY_USERNAME: "{{ lookup('dict', item.value).key }}"
    REGISTRY_PASSWORD: "{{ lookup('dict', item.value).value }}"
    REGISTRY: "{{ item.key }}"
  no_log: false
  loop: "{{ query('dict', tripleo_container_registry_logins) }}"
  register: registry_login_podman
  until: registry_login_podman.rc == 0
  delay: 4
  retries: 20
EOF
# remove volume type create
cp /usr/share/openstack-tripleo-heat-templates/deployment/cinder/cinder-api-container-puppet.yaml /usr/share/openstack-tripleo-heat-templates/deployment/cinder/cinder-api-container-puppet.yaml_BAK
sed -i  '/external_deploy_tasks:/,$d' /usr/share/openstack-tripleo-heat-templates/deployment/cinder/cinder-api-container-puppet.yaml
exit
sudo su
# add proxy for podman login
vim /usr/share/ansible/roles/tripleo-podman/tasks/tripleo_podman_login.yml +21
# remove volume type create
vim /usr/share/openstack-tripleo-heat-templates/deployment/cinder/cinder-api-container-puppet.yaml +503
exit
############################################################################################################################################################################################

sudo openstack tripleo container image prepare -e /home/stack/containers-prepare-parameter.yaml

nohup openstack overcloud deploy --templates \
    -r /home/stack/templates/roles_data.yaml \
    -e /home/stack/containers-prepare-parameter.yaml \
    -e /home/stack/templates/network-environment.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-isolation.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/host-config-and-reboot.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/services/neutron-ovs.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/services/neutron-sriov.yaml \
    --log-file overcloud_install.log &

export http_proxy=http://10.158.100.1:8080/
export https_proxy=http://10.158.100.1:8080/
export http_proxy=http://10.110.44.22:8099 && export https_proxy=http://10.110.44.22:8099
podman login --username=nokia-cloudran-osp --password=nokiacloudran --tls-verify=True registry.redhat.io

# Add controller and compute nodes to /etc/hosts
openstack server list -c Name -c Networks -f value | awk -F ctlplane= '{print $2,$1}' | sudo tee -a /etc/hosts

ssh heat-admin@controller-0
sudo su
cd ~
cat > /etc/ssh/sshd_config << EOF
# File is managed by Puppet
Port 22

AcceptEnv LANG LC_*
ChallengeResponseAuthentication no
HostKey /etc/ssh/ssh_host_rsa_key
HostKey /etc/ssh/ssh_host_ecdsa_key
HostKey /etc/ssh/ssh_host_ed25519_key
PermitRootLogin yes
PasswordAuthentication yes
PrintMotd yes
Subsystem sftp /usr/libexec/openssh/sftp-server
UseDns no
UsePAM yes
X11Forwarding yes
EOF
systemctl restart sshd
sudo iptables -I INPUT -s 10.0.0.0/8 -p tcp -m multiport --dports 22 -m state --state NEW -m comment --comment "omak: Accept ssh from Office subnet ipv4" -j ACCEPT
/sbin/service iptables save
passwd root

scp overcloudrc root@controller-0:~
ssh root@controller-0
source overcloudrc

openstack volume type create --public tripleo
openstack quota set \
    --cores -1 --instances -1 --key-pairs -1 --properties -1 --ram -1 \
    --server-groups -1 --server-group-members -1 --backups -1 --floating-ips -1 \
    --secgroup-rules -1 --secgroups -1 --networks -1 --subnets -1 --ports -1 \
    --routers -1 --rbac-policies -1 --subnetpools -1 \
    --per-volume-gigabytes -1 --volume-type -1 admin

##### Delete overcloud
rm -rf overcloud_install.log
openstack overcloud delete overcloud --yes

openstack stack delete overcloud --yes --wait

for node in $(openstack baremetal node list -c UUID -f value) ; do openstack baremetal node delete $node; done

# 部署完成后相关配置

openstack network create \
    --share --external --project admin \
    --provider-network-type vlan \
    --provider-physical-network tenant \
    --provider-segment 3401 \
    OAM-v4

openstack subnet create \
    --no-dhcp --project admin \
    --ip-version 4 \
    --network OAM-v4 \
    --subnet-range 10.107.169.128/26 \
    --gateway 10.107.169.129 \
    --allocation-pool start=10.107.169.132,end=10.107.169.190 \
    oam-subnet-v4

openstack network create \
    --share --external --project admin \
    --provider-network-type vlan \
    --provider-physical-network tenant \
    --provider-segment 3202 \
    OAM-v6

openstack subnet create \
    --no-dhcp --project admin \
    --ip-version 6 \
    --network OAM-v6 \
    --subnet-range 2a00:8a00:8000:5002:0:f:5:0/112 \
    --gateway 2a00:8a00:8000:5002:0:f:5:1 \
    --allocation-pool start=2a00:8a00:8000:5002:0:f:5:4,end=2a00:8a00:8000:5002:0:f:5:3f \
    oam-subnet-v6

openstack network create \
    --share --project admin \
    --provider-network-type flat \
    --provider-physical-network sriov-a \
    extnet-a

openstack subnet create \
    --no-dhcp --project admin \
    --ip-version 4 \
    --network extnet-a \
    --subnet-range 192.168.2.0/24 \
    --gateway 192.168.2.1 \
    --allocation-pool start=192.168.2.4,end=192.168.2.254 \
    extnet-subnet-a

openstack network create \
    --share --project admin \
    --provider-network-type flat \
    --provider-physical-network sriov-b \
    extnet-b

openstack subnet create \
    --no-dhcp --project admin \
    --ip-version 4 \
    --network extnet-b \
    --subnet-range 192.168.12.0/24 \
    --gateway 192.168.12.1 \
    --allocation-pool start=192.168.12.4,end=192.168.12.254 \
    extnet-subnet-b

openstack project create CBTS1
openstack user create --project CBTS1 --password system123 CBTS1user
openstack role add --project CBTS1 --user CBTS1user admin
openstack quota set \
    --cores 30 --instances 10 --ram 150 --key-pairs -1 --properties -1  \
    --server-groups -1 --server-group-members -1 --backups -1 --floating-ips -1 \
    --secgroup-rules -1 --secgroups -1 --networks -1 --subnets -1 --ports -1 \
    --routers -1 --rbac-policies -1 --subnetpools -1 \
    --per-volume-gigabytes -1 --volume-type -1 CBTS1

adduser CBTS1user
passwd CBTS1user
su - CBTS1user
cat > ~/CBTS1user_rc << EOF
# Clear any old environment that may conflict.
for key in $( set | awk '{FS="="}  /^OS_/ {print $1}' ); do unset $key ; done
export NOVA_VERSION=1.1
export COMPUTE_API_VERSION=1.1
export OS_USERNAME=CBTS1user
export OS_PROJECT_NAME=CBTS1
export OS_USER_DOMAIN_NAME=Default
export OS_PROJECT_DOMAIN_NAME=Default
export OS_NO_CACHE=True
export OS_CLOUDNAME=overcloud
export no_proxy=10.107.196.126,127.0.0.1,192.168.28.0/24,192.168.28.42,localhost,pink,pink.ctlplane,pink.ctlplane.localdomain,pink.localhost
export PYTHONWARNINGS='ignore:Certificate has no, ignore:A true SSLContext object is not available'
export OS_AUTH_TYPE=password
export OS_PASSWORD=system123
export OS_AUTH_URL=http://10.107.196.126:5000
export OS_IDENTITY_API_VERSION=3
export OS_COMPUTE_API_VERSION=2.latest
export OS_IMAGE_API_VERSION=2
export OS_VOLUME_API_VERSION=3
export OS_REGION_NAME=regionOne
EOF

rm -rf /home/CBTS1user/custom_rc
</pre>
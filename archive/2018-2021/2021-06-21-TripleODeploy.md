---
title: "TripleO Deploy"
date: 2021-06-21T16:59:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [23, 27]
url: http://47.84.100.47/?p=266
---


<pre class="wp-block-syntaxhighlighter-code">ip addr add 10.57.199.224/24 dev enp0s3
ip route add default via 10.57.199.1

#1. Log in to your machine and replace repo
scp root@10.57.199.254:~/Maxwell_Li/CentOS7-Base.repo /etc/yum.repos.d/
scp root@10.57.199.254:~/Maxwell_Li/python2-tripleo-repos-0.0.1-0.20181115164350.a5b709e.el7.noarch.rpm ./

cat >> /etc/yum.conf << EOF
proxy=http://10.144.1.10:8080/
proxy=https://10.144.1.10:8080/
EOF

mv /etc/yum.repos.d/CentOS-Base.repo /etc/yum.repos.d/CentOS-Base.repo.backup
yum clean all
yum makecache

yum install -y wget vim net-tools

#2. Create a non-root user:

sudo useradd stack
sudo passwd stack

echo "stack ALL=(root) NOPASSWD:ALL" | sudo tee -a /etc/sudoers.d/stack
sudo chmod 0440 /etc/sudoers.d/stack

su - stack

#3. Enable needed repositories:
export http_proxy=http://10.144.1.10:8080/

sudo yum install -y https://trunk.rdoproject.org/centos7/current/python2-tripleo-repos-0.0.1-0.20181115164350.a5b709e.el7.noarch.rpm
sudo yum install -y /root/python2-tripleo-repos-0.0.1-0.20181115164350.a5b709e.el7.noarch.rpm
sudo -E tripleo-repos -b ocata current ceph

#4. Install the TripleO CLI
sudo yum -y install epel-release
sudo yum install -y python-pip
sudo pip install python-tripleoclient

sudo yum install -y python-tripleoclient

#5. Prepare the configuration file:
cp /usr/share/instack-undercloud/undercloud.conf.sample ~/undercloud.conf

#6. Run the command to install the undercloud:
unset http_proxy
unset https_proxy

openstack undercloud install

################################################################################
#1. Export environment variables
export DIB_YUM_REPO_CONF="/etc/yum.repos.d/delorean*"
export STABLE_RELEASE="ocata"
export DIB_YUM_REPO_CONF="$DIB_YUM_REPO_CONF /etc/yum.repos.d/tripleo-centos-ceph-jewel.repo"

#2. Build the required images:
openstack overcloud image build

#3. Load the images into the containerized undercloud Glance:
openstack overcloud image upload

#4. Modify volume size:
sudo sed -i 's/10280/514000/g' /usr/share/openstack-tripleo-heat-templates/puppet/services/cinder-volume.yaml

#5. Register and configure nodes for your deployment with Ironic:
openstack overcloud node import instackenv.json

#6. Once the undercloud is installed, you can run the pre-introspection validations:
openstack workflow execution create tripleo.validations.v1.run_groups '{"group_names": ["pre-introspection"]}'

#7. Nodes must be in the manageable provisioning state in order to run introspection. Introspect hardware attributes of nodes with:
openstack overcloud node introspect --all-manageable

#8. To move nodes from manageable to available the following command can be used:
openstack overcloud node provide --all-manageable

#9. Define the nameserver to be used for the environment:
openstack subnet set ctlplane-subnet --dns-nameserver 10.56.126.31

#10. Run the deploy command, including any additional parameters as necessary:
openstack overcloud deploy --compute-scale 2 --templates \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-isolation.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-environment.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/neutron-sriov.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/hyperconverged-ceph.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/storage-environment.yaml \
    --log-file overcloud_install.log

openstack overcloud deploy --ceph-storage-scale 1 --templates \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-isolation.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-environment.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/storage-environment.yaml

openstack overcloud deploy --compute-scale 2 --templates \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-isolation.yaml \
    -e /usr/share/openstack-tripleo-heat-templates/environments/network-environment.yaml

</pre>
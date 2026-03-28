---
title: "Shade 与 OpenStack Client 冲突处理"
date: 2017-01-27T09:59:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [5, 11, 25, 26]
url: http://47.84.100.47/?p=107
---


> 问题发现 Ansible 新的 OpenStack 模块依赖 OpenStack…

---

## **问题发现**

Ansible 新的 OpenStack 模块依赖 OpenStack 的 shade 包。一开始在 common 的 role 中就加入 shade 的安装，但在调试 CentOS 时发现安装 shade 之后无法安装 python-openstackclient 的一个依赖包：python-requests.noarch 0:2.10.0-1.el7。而这个依赖包的缺失直接导致无法同步数据库。因此修改为先安装 python-openstackclient，再来安装 shade。然而在本地测试时发现，无法执行 openstack endpoint list 等 list 命令。

## **问题分析**

对于这个问题，可以从以下两个角度来解决：

先装 client 包再装 shade 包。这样操作可以成功部署，并且通过 functest 的 healthcheck 和 vping。因为 functest 是在 docker 上进行测试的，而 docker 上的 client 安装正确。但是在控制节点上，由于 client 错误安装，导致无法使用 list 命令。ERROR 如下：

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# openstack user list --debug
......
Using parameters {'username': 'admin', 'project_name': 'admin', 'user_domain_name': 'default', 'auth_url': 'http://172.16.1.222:35357/v3', 'password': '***', 'project_domain_name': 'default'}
__init__() got an unexpected keyword argument 'app_name'
Traceback (most recent call last):
  File "/usr/local/lib/python2.7/dist-packages/cliff/app.py", line 393, in run_subcommand
    self.prepare_to_run_command(cmd)
  File "/usr/local/lib/python2.7/dist-packages/openstackclient/shell.py", line 198, in prepare_to_run_command
    return super(OpenStackShell, self).prepare_to_run_command(cmd)
  File "/usr/local/lib/python2.7/dist-packages/osc_lib/shell.py", line 452, in prepare_to_run_command
    self.client_manager.setup_auth()
  File "/usr/local/lib/python2.7/dist-packages/openstackclient/common/clientmanager.py", line 81, in setup_auth
    return super(ClientManager, self).setup_auth()
  File "/usr/local/lib/python2.7/dist-packages/osc_lib/clientmanager.py", line 189, in setup_auth
    additional_user_agent=[('osc-lib', version.version_string)],
  File "/usr/local/lib/python2.7/dist-packages/osc_lib/session.py", line 27, in __init__
    super(TimingSession, self).__init__(**kwargs)
  File "/usr/lib/python2.7/dist-packages/positional/__init__.py", line 101, in inner
    return wrapped(*args, **kwargs)
TypeError: __init__() got an unexpected keyword argument 'app_name'
clean_up ListUser: __init__() got an unexpected keyword argument 'app_name'
Traceback (most recent call last):
  File "/usr/local/lib/python2.7/dist-packages/osc_lib/shell.py", line 135, in run
    ret_val = super(OpenStackShell, self).run(argv)
  File "/usr/local/lib/python2.7/dist-packages/cliff/app.py", line 279, in run
    result = self.run_subcommand(remainder)
  File "/usr/local/lib/python2.7/dist-packages/osc_lib/shell.py", line 180, in run_subcommand
    ret_value = super(OpenStackShell, self).run_subcommand(argv)
  File "/usr/local/lib/python2.7/dist-packages/cliff/app.py", line 393, in run_subcommand
    self.prepare_to_run_command(cmd)
  File "/usr/local/lib/python2.7/dist-packages/openstackclient/shell.py", line 198, in prepare_to_run_command
    return super(OpenStackShell, self).prepare_to_run_command(cmd)
  File "/usr/local/lib/python2.7/dist-packages/osc_lib/shell.py", line 452, in prepare_to_run_command
    self.client_manager.setup_auth()
  File "/usr/local/lib/python2.7/dist-packages/openstackclient/common/clientmanager.py", line 81, in setup_auth
    return super(ClientManager, self).setup_auth()
  File "/usr/local/lib/python2.7/dist-packages/osc_lib/clientmanager.py", line 189, in setup_auth
    additional_user_agent=[('osc-lib', version.version_string)],
  File "/usr/local/lib/python2.7/dist-packages/osc_lib/session.py", line 27, in __init__
    super(TimingSession, self).__init__(**kwargs)
  File "/usr/lib/python2.7/dist-packages/positional/__init__.py", line 101, in inner
    return wrapped(*args, **kwargs)
TypeError: __init__() got an unexpected keyword argument 'app_name'

END return value: 1</pre>

先装 shade 包再装 client 包。这样操作的话只有在 CentOS 上才会出现无法安装 python-requests.noarch 包无法安装的问题，Ubuntu 上一切正常。所以只需要解决 CentOS 场景中包的冲突问题。

## **解决方案**

根据此问题，有以下几个解决方案：

### **一、重装 client**

在 OpenStack 安装完成之后，卸载 shade，然后重新安装各个 client。

<pre class="wp-block-syntaxhighlighter-code">- name: remove packages
  pip: name={{ item }} state=absent
  with_items:
    - shade
    - python-openstackclient
    - python-novaclient
    - python-neutronclient
    - python-cinderclient
    - python-glanceclient
    - python-ceilometerclient
    - python-heatclient

- name: install openstackclient
  pip: name={{ item }} state=present
  with_items:
    - python-openstackclient
    - python-novaclient
    - python-neutronclient
    - python-cinderclient
    - python-glanceclient
    - python-ceilometerclient
    - python-heatclient</pre>

此方案经过测试后，能够解决问题。但是这并不能真正解决问题，而是暴力规避。

### **二、使用 virtualenv**

春节期间我在 openstack-infra 的 IRC 频道上提出了这个问题，得到的一致回复是使用 virtualenv。将 shade 装在虚拟环境中，但是 ansible 的 openstack module 需要调用 shade 中的一些函数，主要问题就是如何将外部环境中的 python 脚本调用 虚拟环境中的函数。

此方案能够完全解决问题，但是工作量较大，暂时搁置。

### **三、更换 client 安装方法**

将这个问题发送给了 shade 的 contributor Monty Taylor，并收到了他的回信：

<blockquote class="wp-block-quote is-layout-flow wp-block-quote-is-layout-flow">
> On 02/02/2017 08:33 PM, liyuenan wrote:

> > Hi Mordred

> >

> > I am Yuenan Li, and I had a question about shade.

> > After installed shade, I found I can not install python-openstackclient, there is the log:

> > http://paste.openstack.org/show/596677/

> > But it only happened in CentOS. Ubuntu is ok.

> > And if i install python-openstack firsh, then install shade. The problem is that I can not run openstack endpoint list or some other list commands. There is the log:

> > http://paste.openstack.org/show/596678/

> > This problem happened in CentOS and Ubuntu both.

> >

> > Best Regards!

> > Yuenan Li

>

> Hi!

>

> So – there are a few problems here. First shade and python-openstackclient both depends on python client libraries for OpenStack. If you install one from pip and one from yum, then there can be weird conflicts between the two sets of libraries. We are working on removing the python-*client depends from shade.

>

> For now, I would just skip the yum install of python-openstackclient.

> shade has a transitive depend on it anyway – so you will get python-openstackclient installed via pip when you install shade. (In general, mixing yum/apt and pip installation of python things can break in strange ways.)

>

> Sorry for the trouble!

> Monty

可见这是利用 pip 安装和 yum/apt 安装 client 所产生的冲突。虽然 Monty Taylor 在回信中说明了这是 shade 的问题，并且会努力解决，但是并不知道什么时候能够完全解决。

根据回信中的方法，将所有 python-*client 包改用 pip 安装，然后再安装 shade，但是问题依旧存在。方案失败。

### **四、删除冲突依赖**

在 shade 代码中 requiement.txt 文件中，写明了 shade 对 python-*client 的依赖。

<pre class="wp-block-syntaxhighlighter-code">pbr>=0.11,<2.0

munch
decorator
jmespath
jsonpatch
ipaddress
os-client-config>=1.22.0
requestsexceptions>=1.1.1
six

keystoneauth1>=2.11.0
netifaces>=0.10.4
python-novaclient>=2.21.0,!=2.27.0,!=2.32.0
python-keystoneclient>=0.11.0
python-cinderclient>=1.3.1
python-neutronclient>=2.3.10
python-troveclient>=1.2.0
python-ironicclient>=0.10.0
python-heatclient>=1.0.0
python-designateclient>=2.1.0
python-magnumclient>=2.1.0

dogpile.cache>=0.5.3</pre>

尝试删除所有 python-*client 依赖，重新构建 shade 包及其依赖包。然后加入到 pip-openstack 包中，用新做的 pip-openstack 包来进行部署，问题依然存在。所以 Monty 回信中的 client 冲突并不准确，但是也提供给我一个猜测方向，shade 和 openstack 的某个包冲突。查看文件中的各个依赖包，与 openstack 相关的貌似只有 os-client-config 和 keystoneauth1 这两个包了。而我清楚地记得，这两个包在 openstack 的部署过程中就已经安装过了，尝试删除这两个依赖包，重新做 shade 包。方案成功。

## **问题反思**

因为懒，所以一直不想用 virtualenv，尝试别的方案。更换 shade 版本，从 1.11.0 到 1.15.0 都尝试过。更新 openstack ppa，更新 python-*client 包。对每一次修改再进行先装 shade 后装 client 和 先装 client 后装 shade 的尝试。几天以来部署了无数遍，终于解决了这个问题。剩下的就等 Monty 解决掉这个依赖冲突后，再更新一下就可以了。
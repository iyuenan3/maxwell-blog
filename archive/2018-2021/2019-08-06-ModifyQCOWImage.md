---
title: "Modify QCOW Image"
date: 2019-08-06T16:37:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [27]
url: http://47.84.100.47/?p=250
---


> 把需要的 qcow2 image 下载到 LinSEE 网盘上（注意需要用 ch…

---

把需要的 qcow2 image 下载到 LinSEE 网盘上（注意需要用 chmod 命令将读写权限修改成所有人可以读写，因为到时候需要 root 账号 mount）

如果没有 nbd ，首先执行这两句：

<pre class="wp-block-syntaxhighlighter-code">sudo yum install -y qemu-img
sudo modprobe nbd max_part=8</pre>

root 账号登陆 EECloud，执行以下命令挂载 QCOW2 image:

<pre class="wp-block-syntaxhighlighter-code">qemu-nbd --connect=/dev/nbd3 /root/maxwell/AirFrame
fdisk /dev/nbd3 -l
mount /dev/nbd3p1 /root/maxwell/test
cd test/opt/nokia/lib/
cd test/opt/nokia/libexec/avahi-deployment/</pre>

如果已经有人 mount 了 QCOW2 但还没有 unmout，注意命令中的 /dev/nbd0 可能是 /dev/nbd1

操作好 QCOW2 内容后，用以下命令 unmout：

<pre class="wp-block-syntaxhighlighter-code">cd /root/maxwell
umount /root/maxwell/test
qemu-nbd --disconnect /dev/nbd3
scp AirFrame.qcow2 root@10.57.200.86:/home/sqg04/image</pre>

这个原理是用命令把 QCOW2 image 挂载成网盘，然后再 mount。所以删除时，需要删除磁盘和网盘的挂载点。
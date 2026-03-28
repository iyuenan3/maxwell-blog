---
title: "Ceph user for OpenStack 创建问题处理"
date: 2017-01-13T17:02:00
author: Agent-Max & Maxwell Li
categories: [3]
tags: [5, 9, 10, 11, 25, 27]
url: http://47.84.100.47/?p=67
---


> 问题发现 北京时间 1月 10日中午，部署 nosdn-nofeature 场景…

---

## **问题发现**

北京时间 1月 10日中午，部署 nosdn-nofeature 场景无法拉起实例，CI 上 Yardstick 和 Functest 均无法通过。

## **问题定位**

### **Step1 定位 Bug 由哪个 Patch 引入**

查看 CI 构建历史，Yardstick 最后一次成功为 CI 时间 1月 1日。由于北京时间 12月 31日 – 1月 5日之间，上游社区 compass-core 项目误将分支代码合入主干，导致那段时间内无法部署。由此推断出，12月 31日 – 1月 10日中合入的 Patch 引入了 Bug。此时间段共合入两个 Patch：

<ul class="wp-block-list">
- [Fix instance can’t get key bug](https://gerrit.opnfv.org/gerrit/#/c/26699/)

- [Yamllint test](https://gerrit.opnfv.org/gerrit/#/c/26517/)

回退黄翔宇合入的 Patch “Fix instance can’t get key bug”，发现问题依旧存在。基本可以断定此 Bug 由 Yamllint test 这个 Patch 引入。看来这锅还是自己的。

### **Step2 查看 log 信息，定位问题**

从下往上查看计算节点的 nova-compute.log，发现第一个 ERROR：

<pre class="wp-block-syntaxhighlighter-code">2017-01-12 17:41:34.659 7903 DEBUG oslo_messaging._drivers.amqpdriver [req-b3dbb34a-7a17-4079-9c49-5a8d50cf6c85 d0df0826ab3643be8ca6f89dff4ca8f7 5a347ea194a9424bb4eded4c2f72b404 - - -] CAST unique_id: 50e816393e10466f8cc170219703e204 NOTIFY exchange 'nova' topic 'notifications.error' _send /usr/lib/python2.7/dist-packages/oslo_messaging/_drivers/amqpdriver.py:432
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [req-b3dbb34a-7a17-4079-9c49-5a8d50cf6c85 d0df0826ab3643be8ca6f89dff4ca8f7 5a347ea194a9424bb4eded4c2f72b404 - - -] [instance: 035a4604-ff63-455c-adc5-86ce6e440a41] Build of instance 035a4604-ff63-455c-adc5-86ce6e440a41 aborted: error opening image 035a4604-ff63-455c-adc5-86ce6e440a41_disk at snapshot None
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41] Traceback (most recent call last):
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/compute/manager.py", line 1779, in _do_build_and_run_instance
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     filter_properties)
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/compute/manager.py", line 1939, in _build_and_run_instance
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     'create.error', fault=e)
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/oslo_utils/excutils.py", line 220, in __exit__
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     self.force_reraise()
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/oslo_utils/excutils.py", line 196, in force_reraise
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     six.reraise(self.type_, self.value, self.tb)
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/compute/manager.py", line 1923, in _build_and_run_instance
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     instance=instance)
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/contextlib.py", line 35, in __exit__
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     self.gen.throw(type, value, traceback)
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/compute/manager.py", line 2105, in _build_resources
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     reason=six.text_type(exc))
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41] BuildAbortException: Build of instance 035a4604-ff63-455c-adc5-86ce6e440a41 aborted: error opening image 035a4604-ff63-455c-adc5-86ce6e440a41_disk at snapshot None
2017-01-12 17:41:34.664 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]
2017-01-12 17:41:34.666 7903 DEBUG nova.compute.manager [req-b3dbb34a-7a17-4079-9c49-5a8d50cf6c85 d0df0826ab3643be8ca6f89dff4ca8f7 5a347ea194a9424bb4eded4c2f72b404 - - -] [instance: 035a4604-ff63-455c-adc5-86ce6e440a41] Deallocating network for instance _deallocate_network /usr/lib/python2.7/dist-packages/nova/compute/manager.py:1659</pre>

查看 `/usr/lib/python2.7/dist-packages/nova/compute/manager.py` 2105行所在函数：

<pre class="wp-block-syntaxhighlighter-code">    def _build_resources(self, context, instance, requested_networks,
                         security_groups, image_meta, block_device_mapping):
        resources = {}
        network_info = None
        try:
            LOG.debug('Start building networks asynchronously for instance.',
                      instance=instance)
            network_info = self._build_networks_for_instance(context, instance,
                    requested_networks, security_groups)
            resources['network_info'] = network_info
        except (exception.InstanceNotFound,
                exception.UnexpectedDeletingTaskStateError):
            raise
        except exception.UnexpectedTaskStateError as e:
            raise exception.BuildAbortException(instance_uuid=instance.uuid,
                    reason=e.format_message())
        except Exception:
            # Because this allocation is async any failures are likely to occur
            # when the driver accesses network_info during spawn().
            LOG.exception(_LE('Failed to allocate network(s)'),
                          instance=instance)
            msg = _('Failed to allocate the network(s), not rescheduling.')
            raise exception.BuildAbortException(instance_uuid=instance.uuid,
                    reason=msg)

        try:
            # Verify that all the BDMs have a device_name set and assign a
            # default to the ones missing it with the help of the driver.
            self._default_block_device_names(instance, image_meta,
                                             block_device_mapping)

            LOG.debug('Start building block device mappings for instance.',
                      instance=instance)
            instance.vm_state = vm_states.BUILDING
            instance.task_state = task_states.BLOCK_DEVICE_MAPPING
            instance.save()

            block_device_info = self._prep_block_device(context, instance,
                    block_device_mapping)
            resources['block_device_info'] = block_device_info
        except (exception.InstanceNotFound,
                exception.UnexpectedDeletingTaskStateError):
            with excutils.save_and_reraise_exception():
                # Make sure the async call finishes
                if network_info is not None:
                    network_info.wait(do_raise=False)
        except (exception.UnexpectedTaskStateError,
                exception.VolumeLimitExceeded,
                exception.InvalidBDM) as e:
            # Make sure the async call finishes
            if network_info is not None:
                network_info.wait(do_raise=False)
            raise exception.BuildAbortException(instance_uuid=instance.uuid,
                    reason=e.format_message())
        except Exception:
            LOG.exception(_LE('Failure prepping block device'),
                    instance=instance)
            # Make sure the async call finishes
            if network_info is not None:
                network_info.wait(do_raise=False)
            msg = _('Failure prepping block device.')
            raise exception.BuildAbortException(instance_uuid=instance.uuid,
                    reason=msg)

        try:
            yield resources
        except Exception as exc:
            with excutils.save_and_reraise_exception() as ctxt:
                if not isinstance(exc, (
                        exception.InstanceNotFound,
                        exception.UnexpectedDeletingTaskStateError)):
                    LOG.exception(_LE('Instance failed to spawn'),
                                  instance=instance)
                # Make sure the async call finishes
                if network_info is not None:
                    network_info.wait(do_raise=False)
                # if network_info is empty we're likely here because of
                # network allocation failure. Since nothing can be reused on
                # rescheduling it's better to deallocate network to eliminate
                # the chance of orphaned ports in neutron
                deallocate_networks = False if network_info else True
                try:
                    self._shutdown_instance(context, instance,
                            block_device_mapping, requested_networks,
                            try_deallocate_networks=deallocate_networks)
                except Exception as exc2:
                    ctxt.reraise = False
                    LOG.warning(_LW('Could not clean up failed build,'
                                    ' not rescheduling. Error: %s'),
                                six.text_type(exc2))
                    raise exception.BuildAbortException(
                            instance_uuid=instance.uuid,
                            reason=six.text_type(exc))</pre>

可以看出是由 `yield resources` 命令错误导致异常退出，猜测 nova-compute.log 中还有其他错误，继续往上翻，果然存在以下 ERROR：

<pre class="wp-block-syntaxhighlighter-code">2017-01-12 17:41:32.926 7903 INFO nova.virt.libvirt.driver [req-b3dbb34a-7a17-4079-9c49-5a8d50cf6c85 d0df0826ab3643be8ca6f89dff4ca8f7 5a347ea194a9424bb4eded4c2f72b404 - - -] [instance: 035a4604-ff63-455c-adc5-86ce6e440a41] Creating image
2017-01-12 17:41:32.940 7903 ERROR nova.virt.libvirt.storage.rbd_utils [req-b3dbb34a-7a17-4079-9c49-5a8d50cf6c85 d0df0826ab3643be8ca6f89dff4ca8f7 5a347ea194a9424bb4eded4c2f72b404 - - -] error opening rbd image 035a4604-ff63-455c-adc5-86ce6e440a41_disk
2017-01-12 17:41:32.940 7903 ERROR nova.virt.libvirt.storage.rbd_utils Traceback (most recent call last):
2017-01-12 17:41:32.940 7903 ERROR nova.virt.libvirt.storage.rbd_utils   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/storage/rbd_utils.py", line 75, in __init__
2017-01-12 17:41:32.940 7903 ERROR nova.virt.libvirt.storage.rbd_utils     read_only=read_only))
2017-01-12 17:41:32.940 7903 ERROR nova.virt.libvirt.storage.rbd_utils   File "rbd.pyx", line 1042, in rbd.Image.__init__ (/build/ceph-XmVvyr/ceph-10.2.2/src/build/rbd.c:9862)
2017-01-12 17:41:32.940 7903 ERROR nova.virt.libvirt.storage.rbd_utils PermissionError: error opening image 035a4604-ff63-455c-adc5-86ce6e440a41_disk at snapshot None
2017-01-12 17:41:32.940 7903 ERROR nova.virt.libvirt.storage.rbd_utils
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [req-b3dbb34a-7a17-4079-9c49-5a8d50cf6c85 d0df0826ab3643be8ca6f89dff4ca8f7 5a347ea194a9424bb4eded4c2f72b404 - - -] [instance: 035a4604-ff63-455c-adc5-86ce6e440a41] Instance failed to spawn
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41] Traceback (most recent call last):
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/compute/manager.py", line 2078, in _build_resources
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     yield resources
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/compute/manager.py", line 1920, in _build_and_run_instance
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     block_device_info=block_device_info)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/driver.py", line 2571, in spawn
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     admin_pass=admin_password)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/driver.py", line 2975, in _create_image
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     fallback_from_host)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/driver.py", line 3075, in _create_and_inject_local_root
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     instance, size, fallback_from_host)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/driver.py", line 6547, in _try_fetch_image_cache
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     size=size)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/imagebackend.py", line 216, in cache
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     if not self.exists() or not os.path.exists(base):
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/imagebackend.py", line 835, in exists
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     return self.driver.exists(self.rbd_name)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/storage/rbd_utils.py", line 291, in exists
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     read_only=True):
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/storage/rbd_utils.py", line 83, in __init__
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     driver._disconnect_from_rados(client, ioctx)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/oslo_utils/excutils.py", line 220, in __exit__
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     self.force_reraise()
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/oslo_utils/excutils.py", line 196, in force_reraise
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     six.reraise(self.type_, self.value, self.tb)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/storage/rbd_utils.py", line 75, in __init__
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]     read_only=read_only))
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]   File "rbd.pyx", line 1042, in rbd.Image.__init__ (/build/ceph-XmVvyr/ceph-10.2.2/src/build/rbd.c:9862)
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41] PermissionError: error opening image 035a4604-ff63-455c-adc5-86ce6e440a41_disk at snapshot None
2017-01-12 17:41:32.943 7903 ERROR nova.compute.manager [instance: 035a4604-ff63-455c-adc5-86ce6e440a41]
2017-01-12 17:41:33.130 7903 DEBUG oslo_messaging._drivers.amqpdriver [-] CALL msg_id: 6036c3f61c304fb69832d3c9e1f5949f exchange 'nova' topic 'conductor' _send /usr/lib/python2.7/dist-packages/oslo_messaging/_drivers/amqpdriver.py:448</pre>

基本可以断定 ERROR 是由 glance、cinder、 ceph 以及 nova 等组件配置错误造成的。

### **Step3 验证各个组件配置文件**

当时提 Yamllint test 那个 Patch 时，修改了很多关于 ceph 的内容。由于 ceph 的配置项基本上都是用 sed 命令修改的，为了保证通过 Yamllint test，曾将好几个 sed 命令进行拆分。其中一小部分如下：

<pre class="wp-block-syntaxhighlighter-code">diff --git a/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_conf.yml b/deploy/adapt
index 0496ba9..8451526 100755
--- a/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_conf.yml
+++ b/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_conf.yml
@@ -12,29 +12,113 @@
   when: inventory_hostname in groups['controller']
   tags:
     - ceph_conf_glance
-  ignore_errors: True
+  ignore_errors: "True"
 
 - name: modify glance-api.conf for ceph
-  shell: sed -i 's/^\(default_store\).*/\1 = rbd/g' /etc/glance/glance-api.conf && sed -i '/^\[glance
+  shell: |
+    sed -i 's/^\(default_store\).*/\1 = rbd/g' /etc/glance/glance-api.conf;
+    sed -i '/^\[glance_store/a rbd_store_pool = images' \
+        /etc/glance/glance-api.conf;
+    sed -i '/^\[glance_store/a rbd_store_user = glance' \
+        /etc/glance/glance-api.conf;
+    sed -i '/^\[glance_store/a rbd_store_ceph_conf = /etc/ceph/ceph.conf' \
+        /etc/glance/glance-api.conf;
+    sed -i '/^\[glance_store/a rbd_store_chunk_size = 8' \
+        /etc/glance/glance-api.conf;
+    sed -i '/^\[glance_store/a show_image_direct_url=True' \
+        /etc/glance/glance-api.conf;
   when: inventory_hostname in groups['controller']
   tags:
     - ceph_conf_glance
 
-- name: restart glance
-  shell: rm -f /var/log/glance/api.log && chown -R glance:glance /var/log/glance && service {{ glance
+- name: remove glance-api log
+  shell: |
+    rm -f /var/log/glance/api.log;
+    chown -R glance:glance /var/log/glance;
+  when: inventory_hostname in groups['controller']
+  tags:
+    - ceph_conf_glance
+  ignore_errors: "True"
+
+- name: restart glance service
+  shell: service {{ glance_service }} restart
+  register: result
+  until: result.rc == 0
+  retries: 10
+  delay: 3
   when: inventory_hostname in groups['controller']
   tags:
     - ceph_conf_glance
-  ignore_errors: True</pre>

为此，拉取了控制节点和计算节点的 nova、glance、cinder、ceph 四个组件所有的配置文件，与 Yamllint test 之前正确部署且能够拉起实例的配置文件进行对比，没有发现任何异常。

### **Step4 检查 glance 是否正常**

对 cirros 镜像进行上传和下载，并对比 512 码。可以断定 glance 正常。

<pre class="wp-block-syntaxhighlighter-code">root@host1:~# wget http://download.cirros-cloud.net/0.3.4/cirros-0.3.4-x86_64-disk.img
root@host1:~# glance image-create --name "cirros" --file cirros-0.3.4-x86_64-disk.img --disk-format qcow2 --container-format bare
root@host1:~# glance image-download 547abb03-3dca-4c31-815c-3f2063b50ebb --file cirros.img
root@host1:~# md5sum cirros-0.3.4-x86_64-disk.img 
ee1eca47dc88f4879d8a229cc70a07c6  cirros-0.3.4-x86_64-disk.img
root@host1:~# md5sum cirros.img 
ee1eca47dc88f4879d8a229cc70a07c6  cirros.img</pre>

### **Step5 再次分析 nova-compute.log 中的错误，查看代码**

此步骤由梁哥协助完成。

查看 `/usr/lib/python2.7/dist-packages/nova/virt/libvirt/imagebackend.py` 文件：

<pre class="wp-block-syntaxhighlighter-code">from nova.virt.libvirt.storage import rbd_utils

class Rbd(Image):

    SUPPORTS_CLONE = True

    def __init__(self, instance=None, disk_name=None, path=None, **kwargs):
        if not CONF.libvirt.images_rbd_pool:
            raise RuntimeError(_('You should specify'
                                 ' images_rbd_pool'
                                 ' flag to use rbd images.'))

        if path:
            try:
                self.rbd_name = path.split('/')[1]
            except IndexError:
                raise exception.InvalidDevicePath(path=path)
        else:
            self.rbd_name = '%s_%s' % (instance.uuid, disk_name)

        self.pool = CONF.libvirt.images_rbd_pool
        self.rbd_user = CONF.libvirt.rbd_user
        self.ceph_conf = CONF.libvirt.images_rbd_ceph_conf

        path = 'rbd:%s/%s' % (self.pool, self.rbd_name)
        if self.rbd_user:
            path += ':id=' + self.rbd_user
        if self.ceph_conf:
            path += ':conf=' + self.ceph_conf

        super(Rbd, self).__init__(path, "block", "rbd", is_block_dev=False)

        self.driver = rbd_utils.RBDDriver(
            pool=self.pool,
            ceph_conf=self.ceph_conf,
            rbd_user=self.rbd_user)

    def exists(self):
        return self.driver.exists(self.rbd_name)</pre>

通过参看 `/etc/nova/nova-compute.conf` 查询上面函数中变量的具体值

<pre class="wp-block-syntaxhighlighter-code">[libvirt]
images_rbd_pool = vms
images_rbd_ceph_conf = /etc/ceph/ceph.conf
rbd_user = cinder</pre>

手动验证错误

<pre class="wp-block-syntaxhighlighter-code">root@host4:~# python
>>> from nova.virt.libvirt.storage import rbd_utils
>>> driver = rbd_utils.RBDDriver(pool="vms", ceph_conf="/etc/ceph/ceph.conf", rbd_user="cinder")
>>> driver.exists("c05cf0ee-6f7f-4b6e-8009-a59ed9a4564a_disk")
ERROR:nova.virt.libvirt.storage.rbd_utils:error opening rbd image c05cf0ee-6f7f-4b6e-8009-a59ed9a4564a_disk
Traceback (most recent call last):
  File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/storage/rbd_utils.py", line 75, in __init__
    read_only=read_only))
  File "rbd.pyx", line 1042, in rbd.Image.__init__ (/build/ceph-XmVvyr/ceph-10.2.2/src/build/rbd.c:9862)
PermissionError: error opening image c05cf0ee-6f7f-4b6e-8009-a59ed9a4564a_disk at snapshot None
Traceback (most recent call last):
  File "<stdin>", line 1, in <module>
  File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/storage/rbd_utils.py", line 291, in exists
    read_only=True):
  File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/storage/rbd_utils.py", line 83, in __init__
    driver._disconnect_from_rados(client, ioctx)
  File "/usr/lib/python2.7/dist-packages/oslo_utils/excutils.py", line 220, in __exit__
    self.force_reraise()
  File "/usr/lib/python2.7/dist-packages/oslo_utils/excutils.py", line 196, in force_reraise
    six.reraise(self.type_, self.value, self.tb)
  File "/usr/lib/python2.7/dist-packages/nova/virt/libvirt/storage/rbd_utils.py", line 75, in __init__
    read_only=read_only))
  File "rbd.pyx", line 1042, in rbd.Image.__init__ (/build/ceph-XmVvyr/ceph-10.2.2/src/build/rbd.c:9862)
rbd.PermissionError: error opening image c05cf0ee-6f7f-4b6e-8009-a59ed9a4564a_disk at snapshot None</pre>

到 host3 上尝试 glance 操作 RBD

<pre class="wp-block-syntaxhighlighter-code">root@host3:~# python
>>> from nova.virt.libvirt.storage import rbd_utils

>>> driver = rbd_utils.RBDDriver(pool="images", ceph_conf="/etc/ceph/ceph.conf", rbd_user="glance")
>>> driver.exists("c05cf0ee-6f7f-4b6e-8009-a59ed9a4564a_disk")
False</pre>

分析 `deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_pre.yml` 然后进行手动配置

<pre class="wp-block-syntaxhighlighter-code">ceph osd pool create testc 50
ceph auth get-or-create client.testc mon 'allow r' osd 'allow class-read object_prefix rbd_children, allow rwx pool=images, allow rwx pool=volumes, allow rwx pool=vms'
ceph auth get-or-create client.testc | tee /etc/ceph/ceph.client.testc.keyring && chown glance:glance /etc/ceph/ceph.client.testc.keyring

root@host3:~# python
>>> from nova.virt.libvirt.storage import rbd_utils
>>> driver = rbd_utils.RBDDriver(pool="vms", ceph_conf="/etc/ceph/ceph.conf", rbd_user="testc")
>>> driver.exists("c05cf0ee-6f7f-4b6e-8009-a59ed9a4564a_disk")
False</pre>

### **Step6 再次 Review Ceph 部分代码**

检查修改记录，发现换行处理异常。

<pre class="wp-block-syntaxhighlighter-code">diff --git a/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_pre.yml b/deploy/adapte
index ece4154..3ff9df4 100755
--- a/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_pre.yml
+++ b/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_pre.yml
@@ -62,15 +62,26 @@
   when: inventory_hostname in groups['ceph_adm']
 
 - name: create ceph users for openstack
-  shell: ceph auth get-or-create client.cinder mon 'allow r' osd 'allow class-read object_prefix rbd_children, allow rwx pool=volumes, allow rwx pool=vms, allow rx pool=images' && ceph auth get-or-create client.glance mon 'allow r' osd 'allow class-read object_prefix rbd_children, allow rwx pool=images'
+  shell: |
+    ceph auth get-or-create client.cinder mon 'allow r' osd \
+        'allow class-read object_prefix rbd_children, allow rwx pool=volumes, \
+        allow rwx pool=vms, allow rx pool=images';
+    ceph auth get-or-create client.glance mon 'allow r' osd \
+        'allow class-read object_prefix rbd_children, allow rwx pool=images';
   when: inventory_hostname in groups['ceph_adm']</pre>

引号内换行，影响了命令的完整性。

### **Step7 提交 Patch，修复 Bug**

为了通过 Yamllint test，只能对这一行进行规避，做了如下修改：

<pre class="wp-block-syntaxhighlighter-code">diff --git a/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_pre.yml b/deploy/adapte
index 3ff9df4..a9eb81a 100755
--- a/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_pre.yml
+++ b/deploy/adapters/ansible/roles/ceph-openstack/tasks/ceph_openstack_pre.yml
@@ -61,14 +61,15 @@
     - vms
   when: inventory_hostname in groups['ceph_adm']
 
+# yamllint disable rule:line-length
 - name: create ceph users for openstack
   shell: |
     ceph auth get-or-create client.cinder mon 'allow r' osd \
-        'allow class-read object_prefix rbd_children, allow rwx pool=volumes, \
-        allow rwx pool=vms, allow rx pool=images';
+        'allow class-read object_prefix rbd_children, allow rwx pool=volumes, allow rwx pool=vms, all
     ceph auth get-or-create client.glance mon 'allow r' osd \
         'allow class-read object_prefix rbd_children, allow rwx pool=images';
   when: inventory_hostname in groups['ceph_adm']
+# yamllint enable rule:line-length</pre>

修改后在本地进行测试和验证，然后提交 Patch。

Patch 地址：

<ul class="wp-block-list">
- [FIX Ceph user error](https://gerrit.opnfv.org/gerrit/#/c/26847/)

## **Bug 引入原因**

<ul class="wp-block-list">
- CI verify 取消了 Functest 测试，无法对部署的 OpenStack 进行验证。

- Yamllint test Patch 修改了大量代码，尤其是对 ansible playbook 的换行处理，而 ansible 换行处理极易出错。

## **问题反思**

<ol class="wp-block-list">
- CI verify 必须尽快加入相关测试，例如 Functest 的 vping 用例。

- 对 ceph 与 OpenStack 的对接过程需要进一步了解。

- 提升自己看 log 与定位问题的能力。

- 对于代码修改量较大的 Patch，不仅需要在本地进行端到端测试，更应该跑一遍 Yardstick 和 Functest。
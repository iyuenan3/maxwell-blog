---
title: "Ansible Module for OpenStack Keystone"
date: 2017-01-23T17:51:00
author: Agent-Max & Maxwell Li
categories: [39]
tags: [5, 10, 15, 25, 26, 27]
url: http://47.84.100.47/?p=101
---


> Ansible 版本升级之后更新了 OpenStack 相关的模块，主要是引用了…

---

Ansible 版本升级之后更新了 OpenStack 相关的模块，主要是引用了 Shade。Shade 的开发主要是为了让用户能够自己编写程序或者利用 Ansible 对 OpenStack 进行一些操作。关于 Shade 的介绍，这里就不展开了，详见 [shade’s documentation](http://docs.openstack.org/infra/shade/)。

然而，Ansible 作为运维工具，所开发的 OpenStack 模块主要是为了对 OpenStack 进行访问和操作，例如创建网络，配置安全组，创建用户等等。但是对于 endpoint，Ansible 并没有相关模块，因为这是在 OpenStack 部署环节才需要的操作。追求代码洁癖，以及提高创建 endpoint 的稳定性，特编写 keystone_endpoint 模块以供使用。

## **查看源码**

查看 ansible os_keystone_service 模块源码：

<pre class="wp-block-syntaxhighlighter-code">cloud = shade.operator_cloud(**module.params)

services = cloud.search_services(name_or_id=name,
                                 filters=dict(type=service_type))
 
if len(services) > 1:
    module.fail_json(msg='Service name %s and type %s are not unique' %
        (name, service_type))
elif len(services) == 1:
    service = services[0]
else:
    service = None

if module.check_mode:
    module.exit_json(changed=_system_state_change(module, service))

if state == 'present':
    if service is None:
        service = cloud.create_service(name=name,
            description=description, type=service_type, enabled=True)
        changed = True
    else:
        if _needs_update(module, service):
            service = cloud.update_service(
                service.id, name=name, type=service_type, enabled=enabled,
                description=description)
            changed = True
        else:
            changed = False
    module.exit_json(changed=changed, service=service, id=service.id)

elif state == 'absent':
    if service is None:
        changed=False
    else:
        cloud.delete_service(service.id)
        changed=True
    module.exit_json(changed=changed)</pre>

可知 os_keystone_service 模块调用 shade 中 search_service create_service update_service delete_service 等函数。在 shade 目录下的 operatorcloud.py 文件中找到了这些函数：

<pre class="wp-block-syntaxhighlighter-code">@_utils.valid_kwargs('type', 'service_type', 'description')
def create_service(self, name, enabled=True, **kwargs):
    """Create a service.

    :param name: Service name.
    :param type: Service type. (type or service_type required.)
    :param service_type: Service type. (type or service_type required.)
    :param description: Service description (optional).
    :param enabled: Whether the service is enabled (v3 only)

    :returns: a ``munch.Munch`` containing the services description,
        i.e. the following attributes::
        - id: <service id>
        - name: <service name>
        - type: <service type>
        - service_type: <service type>
        - description: <service description>

    :raises: ``OpenStackCloudException`` if something goes wrong during the
        openstack API call.

    """
...

def search_services(self, name_or_id=None, filters=None):
    """Search Keystone services.

    :param name_or_id: Name or id of the desired service.
    :param filters: a dict containing additional filters to use. e.g.
                    {'type': 'network'}.

    :returns: a list of ``munch.Munch`` containing the services description

    :raises: ``OpenStackCloudException`` if something goes wrong during the
        openstack API call.
    """
...

def delete_service(self, name_or_id):
    """Delete a Keystone service.

    :param name_or_id: Service name or id.

    :returns: True if delete succeeded, False otherwise.

    :raises: ``OpenStackCloudException`` if something goes wrong during
        the openstack API call
    """
...</pre>

这些函数的注释中详细解释了函数的使用方法。考虑到 shade 是 OpenStack 开发的，在这份文件中搜索 endpoint，找到了 endpoint 的相关函数。

## **编写模块**

参照 os_keystone_service，开始编写 keystone_endpoint 模块。为了测试可行性，先完成 list 功能。

<pre class="wp-block-syntaxhighlighter-code">argument_spec = openstack_full_argument_spec(  # noqa: F405
    enabled=dict(default=True, type='bool'),
    name=dict(required=True),
    service_type=dict(required=True),
    state=dict(default='present', choices=['absent', 'present']),
    region=dict(default=None, required=False),
    interface=dict(default=None,
                   choices=['admin', 'internal', 'public']),
    url=dict(default=None, required=False),
)

...

enabled = module.params['enabled']  # noqa: F841
name = module.params['name']
service_type = module.params['service_type']
state = module.params['state']
region = module.params['region']
interface = module.params['interface']
url = module.params['url']

try:
    cloud = shade.operator_cloud(**module.params)
    endpoints = cloud.list_endpoints()
    module.exit_json(ansible_facts=dict(openstack=dict(endpoints=endpoints)))</pre>

测试后输出：

<pre class="wp-block-syntaxhighlighter-code">[root@compass openstack_newton-opnfv2]# ansible-playbook -i inventories/inventory.yml HA-ansible-multinodes.yml -t keystone_create -vvvv

...
"endpoints": [
    {
        "HUMAN_ID": false, 
        "NAME_ATTR": "name", 
        "enabled": true, 
        "human_id": null, 
        "id": "009a973b4ccc4c2dbe2264edb6ae411c", 
        "interface": "public", 
        "links": {
            "self": "http://172.16.1.222:35357/v3/endpoints/009a973b4ccc4c2dbe2264edb6ae411c"
        }, 
        "region": "RegionOne", 
        "region_id": "RegionOne", 
        "service_id": "9f07f419738c4acf8e0f18915169c08e", 
        "url": "http://192.168.116.222:8774/v2.1/%(tenant_id)s"
    },
    ...
    ...
    ...
    {
        "HUMAN_ID": false, 
        "NAME_ATTR": "name", 
        "enabled": true, 
        "human_id": null, 
        "id": "fe53c627afdd488ab9af245bd928dd41", 
        "interface": "public", 
        "links": {
            "self": "http://172.16.1.222:35357/v3/endpoints/fe53c627afdd488ab9af245bd928dd41"
        }, 
        "region": "RegionOne", 
        "region_id": "RegionOne", 
        "service_id": "a740b9524a60401196b5a06a4fd4ba79", 
        "url": "http://192.168.116.222:8776/v2/%(tenant_id)s"
    }
]
...</pre>

可以看到，模块输出了所有的 endpoints，并没有进行任何筛选。os_keystone_service 模块中为了获取指定 service，调用了 shade 中的 search_service 函数，直接传入 service 的 id 或者 name 就可以返回 service 相关信息。原本打算调用 search_endpoint 函数来完成筛选工作，但是由于相同名字的 endpoint 会有 3 个：admin，internal，public。因此 search_endpoint 函数只能够通过传入 endpoint id 来获取相关信息。无奈之下只能利用 service id 和 interface 进行筛选。

<pre class="wp-block-syntaxhighlighter-code">try:
    cloud = shade.operator_cloud(**module.params)

    services = cloud.search_services(name_or_id=name,
                                     filters=dict(type=service_type))

    if len(services) > 1:
        module.fail_json(msg='Service name %s and type %s are not unique' %
                         (name, service_type))
    elif len(services) == 0:
        module.fail_json(msg="No services with name %s" % name)
    else:
        service = services[0]

    endpoints = [x for x in cloud.list_endpoints()
                if (x.service_id == service.id and
                     x.interface == interface)]

    module.exit_json(ansible_facts=dict(openstack=dict(endpoints=endpoints)))</pre>

测试后，成功输出筛选结果：

<pre class="wp-block-syntaxhighlighter-code">[root@compass openstack_newton-opnfv2]# ansible-playbook -i inventories/inventory.yml HA-ansible-multinodes.yml -t keystone_create -vvvv
...
{
    "HUMAN_ID": false, 
    "NAME_ATTR": "name", 
    "enabled": true, 
    "human_id": null, 
    "id": "8d8ac7c39aea42528605d304b547b403", 
    "interface": "public", 
    "links": {
        "self": "http://172.16.1.222:35357/v3/endpoints/8d8ac7c39aea42528605d304b547b403"
    }, 
    "region": "RegionOne", 
    "region_id": "RegionOne", 
    "service_id": "b23733515212450f8d04c7b4a41c3585", 
    "url": "http://192.168.116.222:9292"
}
...</pre>

验证了模块的可用性之后，就可以加入创建、删除、更新等功能。

<pre class="wp-block-syntaxhighlighter-code">count = len(endpoints)
if count > 1:
    module.fail_json(msg='%d endpoints with service name %s' %
                     (count, name))
elif count == 0:
    endpoint = None
else:
    endpoint = endpoints[0]

if module.check_mode:
    module.exit_json(changed=_system_state_change(module, endpoint))

if state == 'present':
    if endpoint is None:
        endpoint = cloud.create_endpoint(
            service_name_or_id=service.id, enabled=enabled,
            region=region, interface=interface, url=url)
        changed = True
    else:
        if _needs_update(module, endpoint):
            endpoint = cloud.update_endpoint(
                endpoint_id=endpoint.id, enabled=enabled,
                service_name_or_id=service.id, region=region,
                interface=interface, url=url)
            changed = True
        else:
            changed = False
    module.exit_json(changed=changed, endpoint=endpoint)

elif state == 'absent':
    if endpoint is None:
        changed = False
    else:
        cloud.delete_endpoint(endpoint.id)
        changed = True
    module.exit_json(changed=changed)</pre>

并进行测试和验证。

Patch 地址：[Ansible Module substitute for Shell Commands](https://gerrit.opnfv.org/gerrit/#/c/27163/)

## **总结**

利用 Shade 提供的 OpenStack 相关函数，显著降低了开发程序的困难度，尤其是对于 Ansible 用户。虽然 Ansible 已经为用户编写好了大部分模块，但任有部分功能缺失。此次模块编写，提高了自己对 Ansible 的掌握程度，进一步了解 Keystone 的鉴权机制。
---
title: "Keystones Token"
date: 2016-12-21T16:51:00
author: Agent-Max & Maxwell Li
categories: [3]
tags: [15]
url: http://47.84.100.47/?p=57
---


> 什么是 Token Token 是一种用户访问凭证，需要使用正确的用户名和密码向…

---

## **什么是 Token**

Token 是一种用户访问凭证，需要使用正确的用户名和密码向Keystone申请后才能够获得。用户通过获得的 Token 作为凭据来访问 OpenStack API。如果用户直接使用用户名和密码来访问 OpenStack API，容易泄露用户信息，造成安全隐患。

在一个典型 OpenStack 环境中，可以指定 Project 凭借用户名和密码来获取 Token。将 project 名传递给环境变量 “OS_PROJECT_NAME“，将用户名传递给环境变量 “OS_USERNAME“，将密码传递给环境变量 “OS_PASSWORD“。然后运行 curl 命令去请求一个 Token。

<pre class="wp-block-syntaxhighlighter-code">$ curl -s -X POST $OS_AUTH_URL/tokens \
  -H "Content-Type: application/json" \
  -d '{"auth": \
          {"tenantName": "'"$OS_PROJECT_NAME"'", "passwordCredentials": \
              {"username": "'"$OS_USERNAME"'", "password": "'"$OS_PASSWORD"'" \
              }}}' | python -m json.tool</pre>

输出如下：

<pre class="wp-block-syntaxhighlighter-code">{
    "access": {
        "metadata": {
            "is_admin": 0,
            "roles": []
        },
        "serviceCatalog": [],
        "token": {
            "audit_ids": [
                "-qPHfpGFT_OLbe-7Qp8Cbw"
            ],
            "expires": "2016-11-18T06:00:51Z",
            "id": "7e44a249251e49788633351d514ab5b9",
            "issued_at": "2016-11-17T18:00:51.203529Z"
        },
        "user": {
            "id": "d762951d561248bda8228b9d4c70e6c2",
            "name": "admin",
            "roles": [],
            "roles_links": [],
            "username": "admin"
        }
    }
}</pre>

当发送 API 请求时，需要将 Token 信息包含在 “X-Auth-Token” 的消息头中。如果同时需要访问多个 OpenStack 服务时，就需要申请多个 Token。Token 只在一定的时间段内有效，并且也会因为一些其他的原因失效。例如当用户角色发生了变更。

OpenStack 发展至今，共产生了 UUID、PKI、PKIZ、Fernet 四种 Token。下面将分别介绍这四种 Token。

## **UUID**

OpenStack D 版本之初，仅有 UUID 类型的 Token 可供使用。UUID Token 是一个长度固定为 32 Byte 的随机字符串，通过 uuid.uuid4().hex 生成。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1292" height="686" src="https://maxwellii.com/wp-content/uploads/2023/11/uuid.png?w=1024" alt="" class="wp-image-33" srcset="http://47.84.100.47/wp-content/uploads/2023/11/uuid.png 1292w, http://47.84.100.47/wp-content/uploads/2023/11/uuid-300x159.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/uuid-1024x544.png 1024w, http://47.84.100.47/wp-content/uploads/2023/11/uuid-768x408.png 768w" sizes="(max-width: 1292px) 100vw, 1292px" /></figure>

UUID Token 虽然简单易用，但是很容易产生性能问题。由于 UUID Token 不携带其他信息，OpenStack API 收到该 Token 后，既不能判断该 Token 是否有效，也无法得知该 Token 携带的用户信息。从上图中可以看出，每当 OpenStack API 接收到用户的请求，就需要向 keystone 验证该 Token 的有效性，并获取用户信息。

UUID Token 简单易用，不携带其他信息，因此 keystone 必须实现 Token 的存储和认证。随着集群规模扩大，Keystone 成了最大的性能瓶颈。

另外，Keystone 不限制用户产生的 Token 数量，大量的 Token 会永久保留在数据库中。当用户频繁发出请求时，Token 的数量增长非常迅速，可以达到几十万条，直接降低 Token 的验证效率。在 Havana 版本中，Keystone 提供 keystone-manage token-flush 命令来清理过期的 Token。并且将 Token 的默认有效时间缩短为一个小时（此前为24小时），避免在大量请求的情况下，数据库中的 Token 太多而影响整个系统的性能。

我们也可以通过一些措施来提高 Keystone 的性能， 例如优化 mysql，使用 memcached 来代替数据库存储 UUID Token，使用 Apache server 来部署 Keystone 等等。这些措施对于单个 Region 和小规模部署的 OpenStack 环境是有效的，但是面对大规模部署，或者是多个 Region 部署，Keystone 将奔溃。主要有两个原因：

<ul class="wp-block-list">
- Keystone 对处理多个并发请的性能低下

- UUID Token 在不同 Region 之间存在同步问题

## **PKI**

于是，从 Folsom 版本开始， 社区提出了 PKI(Public Key Infrastructure) Token，并在 Grizzly 版本中全面支持。在阐述 PKI Token 前，先了解一下公开密钥加密(public-key cryptography)和数字签名。

<ul class="wp-block-list">
- 公开密钥加密，也称为非对称加密(asymmetric cryptography，加密密钥和解密密钥不相同)。在这种密码学方法中，需要一对密钥，分别为公钥(Public Key)和私钥(Private Key)，公钥是公开的，私钥是非公开的，需用户妥善保管。

- 数字签名又称为公钥数字签名，首先采用 Hash 函数对消息生成摘要，摘要经私钥加密后称为数字签名。接收方用公钥解密该数字签名，并与接收消息生成的摘要做对比，如果二者一致，便可以确认该消息的完整性和真实性。

PKI 使用密钥对来实现服务本身的离线认证，Keystone 用私钥对 Token 进行数字签名，各个 API server 用公钥在本地验证该 Token。离线认证步骤如下：

<ol class="wp-block-list">
- Keystone 服务器生成一对密钥，对于 OpenStack 中的服务，每个服务都会拥有一份 Keystone 的公钥，废弃名单和 CA 证书。

- 当 Keystone 接收到生成 Token 的请求，会创建 json 格式的对象，该对象包含了用户所授权的用户组、服务目录和 meta data 等信息。

- Keystone 会对这个 json 使用签名证书和私钥来签名和编码，生成 CMS 格式的Token。值得注意的是，在这个过程中，并没有对 Token 进行加密，如果此时 Token 在传输过程中被黑客截取，用户信息将会暴露。

- 用户可以使用这个 Token 来请求操作。

如下图所示:

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="985" height="930" src="https://maxwellii.com/wp-content/uploads/2023/11/pki_token.png?w=985" alt="" class="wp-image-34" srcset="http://47.84.100.47/wp-content/uploads/2023/11/pki_token.png 985w, http://47.84.100.47/wp-content/uploads/2023/11/pki_token-300x283.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/pki_token-768x725.png 768w" sizes="(max-width: 985px) 100vw, 985px" /></figure>

例如用户使用 Token 来请求 Nova 服务创建虚拟机。Nova 在收到 API 请求后，会对 PKI Token 进行解码，拿到编码前的 json 对象。在这个对象里已经包含了 Token 的有效期及其它相关信息，因此 Nova 不需要再请求 Keystone 来验证这个 Token，从而实现离线的验证。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1382" height="644" src="https://maxwellii.com/wp-content/uploads/2023/11/pki.png?w=1024" alt="" class="wp-image-35" srcset="http://47.84.100.47/wp-content/uploads/2023/11/pki.png 1382w, http://47.84.100.47/wp-content/uploads/2023/11/pki-300x140.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/pki-1024x477.png 1024w, http://47.84.100.47/wp-content/uploads/2023/11/pki-768x358.png 768w" sizes="(max-width: 1382px) 100vw, 1382px" /></figure>

和 UUID 相比，PKI Token 携带更多用户信息的同时还附上了数字签名，以支持本地认证，从而分流了 Keystone 服务的工作负载。但这种 Token 格式也存在两个较大的问题。

<ul class="wp-block-list">
- PKI Token 本身的性能问题。通过 OpenStack wiki 上的 KeystonePerformance 一文可以看出，PKI 的性能是低于 UUID 的，尤其是对于创建 Token 的压力测试情景。

- PKI Token 的长度问题。PKI 编码和签名方式是基于整个 json 对象的，如果在服务目录里 endpoint 比较多的情况下，获取到的 Token 会轻松超过 8K。而 HTTP header 的长度是有限制的，Apache 服务的长度限制是 8K，如果使用 haproxy 做负载均衡，haproxy 默认支持 4K。可以通过重新编译 Apache 或者 haproxy 来增加更长的 header，但并不能解决根本问题。

除此之外，PKI Token 还有和 UUID Token 同样的问题，就是 Token 的持久化带来的对系统性能的开销，以及在多个 Region 部署时 Token 同步所带来的系统设计复杂性的开销。

## **PKIZ**

PKIZ 在 PKI 的基础上利用 zlib 对 Token 进行压缩处理，但是压缩的效果极其有限，一般情况下，压缩后的大小为 PKI Token 的 90% 左右，所以 PKIZ 并不能友好的解决 Token size 太大问题。

## **Fernet**

前三种 Token 都会持久性存于数据库，与日俱增积累的大量 Token 引起数据库性能下降。为了避免该问题，社区在 Kilo 版本提出了 Fernet Token，它携带了少量的用户信息，采用了对称加密，无需存于数据库中。

Fernet 是专为 API token 设计的一种轻量级安全消息格式，不需要存储于数据库，减少了磁盘的 IO，带来了一定的性能提升。并且可以通过使用 Key Rotation 更换密钥来提高安全性。

<ul class="wp-block-list">
- Fernet Token 使用 Fernet 标准。Fernet 是一种采用 Cryptography 对称加密库（symmetric cryptography，加密密钥和解密密钥相同）方法的认证系统实现，广泛应用于 Heroku 项目。简单来说，Fernet 使用 AES-CBC 来加密并使用 SHA256 散列函数来签名用户提供的信息，其中包含了加密时的时间戳信息。这个 Token 中所包含的信息只能使用加密和签名时使用的 Key 来读取和更改。

- Keystone 中的 Fernet Token 不使用任何持久化的后端。相反，对于多个 Keystone 节点的部署，只需要将相同的 Key 同步到所有的 Keystone 节点上，然后无论通过哪个节点所生成的 Token，都可以被其他 Keystone 节点所验证。

<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1464" height="614" src="https://maxwellii.com/wp-content/uploads/2023/11/fernet.png?w=1024" alt="" class="wp-image-36" srcset="http://47.84.100.47/wp-content/uploads/2023/11/fernet.png 1464w, http://47.84.100.47/wp-content/uploads/2023/11/fernet-300x126.png 300w, http://47.84.100.47/wp-content/uploads/2023/11/fernet-1024x429.png 1024w, http://47.84.100.47/wp-content/uploads/2023/11/fernet-768x322.png 768w" sizes="(max-width: 1464px) 100vw, 1464px" /></figure>

最后，Fernet Token 只加密必要的信息，长度一般不超过 255Byte，从而避免了 Token 过大的问题。

## 参考资料

<div class="wp-block-jetpack-markdown">[Understanding OpenStack Authentication: Keystone PKI](https://www.mirantis.com/blog/understanding-openstack-authentication-keystone-pki/)

[OpenStack ReleaseNotes Grizzly](https://wiki.openstack.org/wiki/ReleaseNotes/Grizzly#OpenStack_Identity_.28Keystone.29)

[KeystonePerformance](https://wiki.openstack.org/wiki/KeystonePerformance)

[Benchmarking OpenStack Keystone token formats](http://dolphm.com/benchmarking-openstack-keystone-token-formats/)

[Support Compression of the PKI token](https://blueprints.launchpad.net/keystone/+spec/compress-tokens)

[Compressed tokens](http://adam.younglogic.com/2014/02/compressed-tokens/)

[Fernet (symmetric encryption)](https://cryptography.io/en/latest/fernet/)

[Fernet Spec](https://github.com/fernet/spec/blob/master/Spec.md)

</div>
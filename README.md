# CareerCompass WordPress Theme

> **一个 Agent 和一个懒人的博客主题**  
> 版本：1.0.0 | 作者：Agent-Max & Maxwell Li

---

## 📖 项目介绍

CareerCompass 是一个专为技术博客设计的 WordPress 子主题，基于 [Underscores (_s)](https://underscores.me/) 构建。

### 特色

- 🎨 **CareerCompass 配色方案** - 蓝紫渐变 (#667eea → #764ba2)
- 📱 **完全响应式** - 完美支持移动、平板、桌面
- ⚡ **轻量级** - 原生 CSS 和 JavaScript，无构建步骤
- 🌐 **SEO 友好** - HTML5 语义化标签
- ♿ **无障碍** - 符合 WCAG 2.0 标准
- 🚀 **性能优化** - 系统字体栈，最小化外部依赖

---

## 🚀 快速开始

### 系统要求

- WordPress 5.9+
- PHP 7.4+
- MySQL 5.6+ 或 MariaDB 10.1+

### 安装步骤

1. **下载主题**
   ```bash
   git clone https://github.com/iyuenan3/maxwell-blog.git careercompass
   ```

2. **安装父主题**
   - 下载 [Underscores (_s)](https://underscores.me/)
   - 上传到 `wp-content/themes/underscores/`

3. **激活主题**
   - 登录 WordPress 后台
   - 外观 → 主题
   - 找到 "CareerCompass"
   - 点击"激活"

4. **配置菜单**
   - 外观 → 菜单
   - 创建主菜单
   - 设置菜单位置为"主菜单"

---

## 🎨 Design Token

### 颜色系统

```css
:root {
  --cc-primary-500: #667eea;      /* 主色 */
  --cc-secondary-800: #764ba2;    /* 辅色 */
  --cc-accent-500: #f093fb;       /* 强调色 */
}
```

### 字体系统

```css
:root {
  --cc-font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Noto Sans CJK SC', sans-serif;
  --cc-font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
```

---

## 📁 文件结构

```
careercompass/
├── style.css              # 主题声明 + 样式
├── functions.php          # 主题功能
├── header.php             # 页眉
├── footer.php             # 页脚
├── front-page.php         # 首页模板
├── index.php              # 文章列表
├── single.php             # 文章页面
├── page.php               # 页面模板
├── assets/
│   ├── images/            # 图片资源
│   │   ├── logo1.png      # 3D 场景插画
│   │   ├── logo2.png      # 完整 Logo
│   │   ├── favicon.png    # 站点图标
│   │   └── max.jpg        # Agent-Max 头像
│   ├── js/
│   │   └── navigation.js  # 导航脚本
│   └── css/               # 样式文件
├── inc/                   # 辅助文件
└── languages/             # 国际化文件
```

---

## 🛠️ 开发指南

### 自定义配色

编辑 `style.css` 中的 CSS 变量：

```css
:root {
  --cc-primary-500: #你的颜色;
  --cc-secondary-800: #你的颜色;
}
```

### 添加新功能

在 `functions.php` 中添加：

```php
function my_custom_function() {
    // 你的代码
}
add_action('wp_head', 'my_custom_function');
```

### 创建子主题

1. 创建新目录 `wp-content/themes/careercompass-child/`
2. 创建 `style.css`：
   ```css
   /*
   Theme Name: CareerCompass Child
   Template: careercompass
   */
   ```
3. 创建 `functions.php`：
   ```php
   <?php
   add_action('wp_enqueue_scripts', 'child_enqueue_styles');
   function child_enqueue_styles() {
       wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
   }
   ```

---

## 📝 更新日志

### 1.0.0 (2026-03-27)

- ✨ 初始版本发布
- 🎨 CareerCompass 配色方案
- 📱 完全响应式设计
- 🚀 性能优化
- ♿ 无障碍支持

---

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

1. Fork 本项目
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

---

## 📄 许可证

本项目采用 GPL v2 或更高版本许可证。

---

## 👥 关于作者

- **Agent-Max** - AI 项目主管 | [GitHub](https://github.com/iyuenan3)
- **Maxwell Li** - 软件部主管 | [LinkedIn](https://linkedin.com/in/iyuenan3)

---

## 🔗 相关链接

- [WordPress.org](https://wordpress.org/)
- [Underscores (_s)](https://underscores.me/)
- [WordPress 主题开发手册](https://developer.wordpress.org/themes/)

---

**CareerCompass** - 一个 Agent 和一个懒人的博客主题 ❤️

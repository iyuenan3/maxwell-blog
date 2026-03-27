# Beep Clone WordPress Theme

> **一个 Agent 和一个懒人的博客主题**  
> 版本：3.0.0 | 作者：Agent-Max & Maxwell Li  
> 风格：1:1 抄袭 WordPress.com Beep 主题

---

## 📖 项目介绍

**Beep Clone** 是一个极客风格的 WordPress 主题，1:1 抄袭 WordPress.com 的付费主题 [Beep](https://wordpress.com/theme/beep)。

灵感来自 "code is poetry"，这是一个为欣赏代码之美的人设计的极客主题。它模仿终端提示符和代码编辑器的风格，采用深色配色方案和等宽字体。

### 特色

- 🖥️ **终端/代码编辑器风格** - 极客专属，仿佛在看代码
- ⌨️ **等宽字体** - Roboto Mono + Noto Sans Mono CJK SC
- 🎨 **深色主题** - 深色背景 (#1a1a1a) + 鲜艳点缀
- 🌈 **动态强调色** - 博客首页（绿色）、文章页（橙色）、页面（黄色）
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
   git clone https://github.com/iyuenan3/maxwell-blog.git beep-clone
   ```

2. **上传到 WordPress**
   - 将 `beep-clone` 文件夹上传到 `wp-content/themes/`
   - 或者在 WordPress 后台：外观 → 主题 → 添加 → 上传主题

3. **激活主题**
   - 外观 → 主题
   - 找到 "Beep Clone"
   - 点击"启用"

4. **配置菜单**
   - 外观 → 菜单
   - 创建主菜单
   - 添加页面（首页、技术笔记、AI 实验、生活记录、归档、关于）
   - 设置菜单位置为"主菜单"

---

## 🎨 Design Token

### 颜色系统

```css
:root {
  /* 深色背景 - 终端风格 */
  --beep-bg-dark: #1a1a1a;
  --beep-bg-darker: #0d0d0d;
  --beep-bg-light: #2a2a2a;
  
  /* 文字颜色 */
  --beep-text-primary: #e0e0e0;
  --beep-text-secondary: #a0a0a0;
  --beep-text-muted: #666666;
  
  /* 强调色 - 根据页面类型变化 */
  --beep-accent-blog: #10B981;    /* 亮绿色 - 博客首页 */
  --beep-accent-post: #F97316;    /* 亮橙色 - 文章页 */
  --beep-accent-page: #EAB308;    /* 亮黄色 - 页面 */
  
  /* 代码风格颜色 */
  --beep-code-green: #10B981;
  --beep-code-orange: #F97316;
  --beep-code-yellow: #EAB308;
  --beep-code-blue: #3B82F6;
  --beep-code-purple: #8B5CF6;
  --beep-code-red: #EF4444;
}
```

### 字体系统

```css
:root {
  --beep-font-mono: 'Roboto Mono', 'Noto Sans Mono CJK SC', monospace;
  --beep-font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Noto Sans', sans-serif;
}
```

---

## 📁 文件结构

```
beep-clone/
├── style.css              # 主题声明 + 样式
├── functions.php          # 主题功能
├── header.php             # 页眉
├── footer.php             # 页脚
├── front-page.php         # 首页模板
├── index.php              # 文章列表
├── single.php             # 文章页面
├── page.php               # 页面模板
├── blog.php               # 博客归档页面
├── assets/
│   ├── images/            # 图片资源
│   │   ├── favicon.png    # 站点图标
│   │   └── ...
│   └── js/
│       └── navigation.js  # 导航脚本
└── README.md              # 本文档
```

---

## 🛠️ 开发指南

### 自定义颜色

编辑 `style.css` 中的 CSS 变量：

```css
:root {
  --beep-accent-blog: #你的颜色;
  --beep-accent-post: #你的颜色;
  --beep-accent-page: #你的颜色;
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

1. 创建新目录 `wp-content/themes/beep-clone-child/`
2. 创建 `style.css`：
   ```css
   /*
   Theme Name: Beep Clone Child
   Template: beep-clone
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

### 3.0.0 (2026-03-27) - Beep Clone

- ✨ 1:1 抄袭 WordPress.com Beep 主题
- 🎨 深色终端风格
- ⌨️ Roboto Mono 等宽字体
- 🌈 动态强调色（绿/橙/黄）
- 🖥️ 代码块风格卡片
- > 终端提示符风格（">" 前缀）
- ✨ 光标动画效果

### 2.0.0 (2026-03-27) - Beep 风格

- 🎨 参考 Beep 主题风格
- ⌨️ 等宽字体
- 📋 文章分类导航
- 🃏 粗边框卡片设计

### 1.0.0 (2026-03-27) - 初始版本

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
- [WordPress.com Beep 主题](https://wordpress.com/theme/beep)

---

**Beep Clone** - 代码即诗歌 🖥️

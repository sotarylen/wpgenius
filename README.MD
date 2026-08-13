# WP Genius

一个功能强大的 WordPress 内容管理与网站优化工具集合插件。

## 简介

WP Genius 将多个实用的 WordPress 功能整合为一个统一的插件平台，帮助网站管理员更高效地进行内容创作、媒体管理和系统优化。

## 主要功能模块

### Word to Post（文档转文章）
将 Word 文档（.docx）导入并自动转换为 WordPress 文章，支持章节自动拆分。
- 上传并解析 DOCX 文件
- 自动识别章节标题
- 批量发布为文章
- 章节索引修复工具

### Media Engine（媒体引擎）
统一的媒体处理中心，整合以下功能：
- **图片水印**：为上传的图片自动添加水印
- **媒体格式转换**：支持将图片转换为 WebP 格式，优化加载速度
- **剪贴板上传**：直接从剪贴板粘贴图片上传

### Smart AUI Lite（智能图片上传）
针对大文件优化的远程图片下载工具，内存占用更低。
- 自动下载文章中的外部图片到本地媒体库
- 支持多线程并发下载
- 视频捕获功能
- 自动设置文章特色图片
- 进度可视化界面
- 兼容 MinIO/S3 等对象存储

### Auto Publish（自动发布）
自动发布草稿文章，支持定时任务和手动批量发布。
- 支持定时发布（每5/15/30分钟或每小时）
- 批量自动发布草稿
- 支持手动触发发布流程

### Frontend Enhancement（前端增强）
提升前端用户体验的工具集。
- **Lightbox**：集成轻量级图片灯箱效果
- **视频优化**：优化视频播放体验
- **阅读模式**：为特定内容提供专注阅读体验

### Post Duplicator（文章复制）
快速复制文章、页面及自定义文章类型。
- 复制文章内容、标题、摘要
- 复制自定义字段和分类法
- 支持所有文章类型

### SMTP Mailer（SMTP 邮件）
配置 SMTP 邮件发送服务，替代默认的 PHP mail()。
- 支持自定义 SMTP 服务器、端口、加密方式
- 支持测试邮件发送
- 确保邮件送达率

### Accelerate（网站加速与清理）
优化 WordPress 后台体验和更新行为控制。
- **后台清理**：隐藏不需要的 Admin Bar 项目和仪表盘小工具
- **更新控制**：管理自动更新行为
- **本地头像**：使用本地上传的图片作为用户头像
- **上传重命名**：自定义上传文件的命名规则
- 禁用月份下拉筛选，优化数据库查询

### System Health（系统健康）
数据库维护和内容优化工具。
- 清理文章修订版
- 清理自动草稿
- 清理孤立元数据
- 清理临时数据
- 扫描并移除文章中的外链图片
- 扫描重复文章

## 安装要求

- WordPress 5.0+
- PHP 7.4+
- PHPWord 库（用于 Word 文档解析，模块内已集成）

## 安装方法

1. 下载插件文件
2. 将文件夹上传到 `/wp-content/plugins/` 目录
3. 在 WordPress 后台的"插件"页面启用 WP Genius
4. 进入「工具」→「WP Genius Modules」进行模块配置

## 模块管理

所有模块默认启用核心模块（Smart AUI Lite、System Health），其他模块可根据需求选择性启用。

1. 访问 WP Genius 设置页面
2. 在「模块管理」标签页中切换各模块的启用状态
3. 点击「保存模块」应用更改
4. 启用后的模块会显示在标签页列表中，可进行详细配置

## 详细配置

### Word to Post 配置

在「Word to Post」标签页中：
- 上传 DOCX 文件进行导入
- 查看已扫描的文件列表
- 清理上传文件夹

### Media Engine 配置

在「Media Engine」标签页中：

**Media Turbo 设置：**
- 启用/禁用 WebP 转换
- 设置 WebP 图片质量
- 设置最小文件大小阈值
- 选择扫描模式（媒体库或文章）
- 设置批处理大小

**剪贴板上传设置：**
- 启用/禁用剪贴板上传
- 设置图片前缀名称

### Smart AUI Lite 配置

在「Smart AUI Lite」标签页中（由 Smart Auto Upload Images 插件提供原生设置界面）：
- 基础 URL 配置
- 图片命名规则
- Alt 文本规则
- 图片尺寸限制
- 排除域名
- 并发线程数
- 重试次数
- 自动设置特色图片
- 进度界面显示
- 视频捕获

### Auto Publish 配置

在「Auto Publish」标签页中：
- 启用/禁用定时发布
- 设置发布间隔（5分钟、15分钟、30分钟、每小时）
- 设置单次发布数量

### Frontend Enhancement 配置

在「Frontend Enhancement」标签页中：
- 启用/禁用 Lightbox
- 启用/禁用视频优化
- 启用/禁用代码高亮

### SMTP Mailer 配置

在「SMTP Mailer」标签页中：
- SMTP 服务器地址
- 端口号
- 认证方式（SSL/TLS）
- 用户名和密码
- 发件人邮箱和名称
- 发送测试邮件

### Accelerate 配置

在「Accelerate」标签页中：

**后台清理：**
- 移除 Admin Bar 图标和菜单项
- 隐藏仪表盘小工具

**更新行为：**
- 禁用插件自动更新
- 禁用主题自动更新
- 移除更新提示

**本地头像：**
- 启用本地头像功能

**上传重命名：**
- 启用自定义上传命名
- 设置命名规则（支持变量：{timestamp}、{sanitized}、{rand}、{date}、{user_id}、{orig}、{ext} 等）

### System Health 配置

在「System Health」标签页中：
- 查看系统信息
- 查看统计数据
- 执行数据库清理操作
- 扫描外链图片并移除
- 扫描重复文章

## 使用说明

### 导入 Word 文档

1. 在 Word to Post 模块中点击「上传 DOCX 文件」
2. 选择本地 .docx 文件
3. 系统将自动解析并创建文章
4. 每章将创建为一篇独立文章

### 自动下载远程图片

1. 确保 Smart AUI Lite 模块已启用
2. 在文章编辑时，外部图片将自动下载到媒体库
3. 图片 URL 将被替换为本地地址
4. 可在设置中调整下载行为

### 自动发布文章

1. 启用 Auto Publish 模块
2. 在设置中开启定时任务（如每5分钟）
3. 系统将自动检测草稿文章并按计划发布

### 清理数据库

1. 在 System Health 模块中查看当前统计
2. 选择需要清理的项目（修订版、草稿等）
3. 执行清理操作

## 开发说明

### 目录结构

```
wp-genius/
├── wp-genius.php              # 主插件文件
├── includes/
├──- `includes/class-abstract-module.php`: 模块抽象基类
│   ├── class-admin-settings.php # 管理设置页面
│   ├── class-module-loader.php  # 模块加载器
│   └── modules/              # 各功能模块目录
│       ├── word-to-post/
│       ├── media-engine/
│       ├── smart-aui/
│       ├── auto-publish/
│       ├── frontend-enhancement/
│       ├── post-duplicator/
│       ├── smtp-mailer/
│       ├── accelerate/
│       └── system-health/
├── assets/
│   ├── css/                  # 样式文件
│   ├── js/                   # 脚本文件
│   └── fonts/                # 字体文件
└── languages/                # 国际化文件
```

### 添加新模块

1. 在 `includes/modules/` 目录下创建新模块文件夹
2. 创建 `module.php` 文件，继承 `W2P_Abstract_Module` 类
3. 实现必需的方法：`id()`、`name()`、`description()`、`init()`
4. 在模块管理页面中会自动识别并显示

### 钩子与过滤器

插件提供以下供开发者使用的钩子：

- `w2p_word_publish_activated` - Word to Post 模块激活时
- `w2p_smart_aui_activated` - Smart AUI 模块激活时
- `w2p_accelerate_activated` - Accelerate 模块激活时
- `w2p_smart_aui_progress` - 图片处理进度（用于自定义进度显示）

## 常见问题

**Q: 导入 Word 文档时提示 PHPWord 错误？**
A: 请确保 `includes/modules/word-to-post/library/` 目录下已通过 Composer 安装依赖。

**Q: Smart AUI Lite 内存占用高？**
A: 该模块专为大文件优化，但仍建议根据服务器配置调整并发线程数。

**Q: 如何禁用特定模块？**
A: 在「WP Genius Modules」设置页面的「模块管理」标签页中，可以单独启用或禁用每个模块。

## 更新日志

### 1.2.0
- 安全加固：API 密钥/DB 密码对称加密存储（W2P_Crypto）、SQL 注入面清零、CSRF 面消除、命名冲突消除
- 性能优化：模块按需加载、CSF 框架仅 admin 加载
- 数据层：AI 调度迁入自定义表、设置访问统一门面、队列断点续跑
- 工程化：phpcs 全量清零、模块类名 W2P_ 前缀统一

### 1.0.1
- 优化内存使用
- 改进大文件处理能力
- 新增视频捕获功能
- 增强系统健康检查工具

## 许可证

MIT License

## 作者

Sotary

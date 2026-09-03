# WP Genius

> 一个功能强大的 WordPress 内容管理、媒体处理与网站优化工具集合插件。

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4) ![License](https://img.shields.io/badge/License-MIT-green) ![Version](https://img.shields.io/badge/Version-2.0.20260903-orange)

## 简介

WP Genius 将多个实用的 WordPress 功能整合为一个统一的模块化插件平台，帮助网站管理员更高效地完成**内容创作、媒体管理、章节导入与系统优化**。所有模块按需启用（懒加载），仅在启用时加载对应代码，保证站点性能。

## 功能模块

### 🤖 AI 内容引擎（ai-engine）
AI 驱动的自动化内容生成，支持多模型提供商（OpenAI / Anthropic / Gemini / DeepSeek）、提示词引擎、内容队列与定时调度（自定义表 `w2p_ai_queue` / `w2p_ai_schedules`）。

### 📚 小说管理（novel-manager）
小说与章节综合管理，依赖 ACF 与 `novel` / `chapter` 自定义文章类型：
- **批量导入**：DOCX / TXT 文档解析，两阶段预览微调 + 断点续传批量发布
- **索引重构**：自动识别分卷、中文数字/混合序号解析、批量重排章节序号
- **级联删除**：删除小说时同步清理全部章节（含进度弹窗）

### 🖼 媒体引擎（media-engine）
统一媒体处理中心：
- 批量转换（WebP）、内容 URL 与格式修复（含 MinIO WebP 探测）
- 残留媒体审计、剪贴板上传、批量工作流（共享控制台与日志）

### 🧠 智能 AUI（smart-aui）
远程图片/媒体增强工具：
- 文章外链媒体筛选与批量移动（两阶段覆盖索引 + transient 缓存）
- 批量下载、线程图片预览、扫描日志与进度弹窗

### 🎭 演员扫描（actor-scanner）
面向人群分类法的演员图库工具：
- gfriends 索引同步（GitHub / jsDelivr）、演员匹配（`W2P_Actor_Matcher`）
- 批量检测、CLI 同步工具；依赖 ACF 与 `humans` 分类法（启动时防御性校验）

### ⏰ 自动发布（auto-publish）
定时（每 5/15/30 分钟或每小时）与手动批量发布草稿文章。

### 🚀 前端增强（frontend-enhancement）
Lightbox 灯箱、视频优化、阅读模式、代码高亮。

### 📋 文章复制（post-duplicator）
复制任意文章类型（含自定义字段与分类法）。

### 🩺 系统健康（system-health）
数据库清理（修订版/自动草稿/孤立元数据/临时数据）、外链图片扫描、重复文章检测。

### ⚡ 网站加速（accelerate）
后台清理、更新控制、本地头像、上传重命名、按月筛选开关（按文章类型多选）。

### 📧 SMTP 邮件（smtp-mailer）
自定义 SMTP 服务器/端口/加密方式，测试邮件发送，确保送达率。

## 安装要求

- WordPress 5.0+
- PHP 7.4+
- 可选：ACF 插件（novel-manager、actor-scanner 依赖）
- 可选：PHPWord 库（novel-manager 解析 DOCX 用，模块内已集成）

## 安装方法

1. 将插件文件夹上传到 `/wp-content/plugins/`，或通过「插件 → 安装插件 → 上传插件」。
2. 在后台「插件」页面启用 WP Genius。
3. 进入「工具 → WP Genius 设置」启用并配置模块。

## 模块管理

- 在设置页「模块管理」标签页切换各模块启用状态并保存。
- 启用后的模块出现在对应标签页中，可进行详细配置。
- 未启用的模块不会被加载（懒加载），不影响站点性能。

## 安全设计

- API 密钥等敏感数据使用站点专属密钥加密存储（libsodium / OpenSSL AES-256-GCM，密钥由 `wp_salt` 派生）。
- 所有输入 sanitize、输出 escape，状态变更操作校验 Nonce 与权限。
- AJAX 处理器强制对象级权限校验；ABSPATH 守卫与目录 `index.php` 哨兵防目录列举。

## 开发说明

### 目录结构

```
wp-genius/
├── wp-genius.php              # 主插件文件（入口、常量、生命周期）
├── includes/
│   ├── class-abstract-module.php   # 模块抽象基类
│   ├── class-module-loader.php     # 模块加载器（懒加载）
│   ├── class-admin-settings.php    # 设置框架（CSF）
│   ├── class-task-queue.php        # WP-Cron 任务队列封装
│   ├── class-security.php          # W2P_Crypto 加密工具
│   ├── class-logger.php            # 日志
│   ├── csf/                        # Codestar Framework（仅 admin 加载）
│   └── modules/                    # 各业务模块（按需加载）
├── assets/                        # 核心样式与脚本
├── docs/                          # 架构图与设计文档
├── languages/                     # 国际化
└── CHANGELOG.md                   # 更新日志
```

### 添加新模块

1. 在 `includes/modules/` 下创建模块文件夹。
2. 创建 `module.php`，继承 `W2P_Abstract_Module`。
3. 实现 `id()`、`name()`、`description()`、`init()` 与可选生命周期方法。
4. 模块管理页自动识别并显示。

### 工程规范

- 代码规范：PHPCS + WPCS（`composer phpcs`）；提交前 `php -l` 语法检查。
- 架构文档：`docs/wpgenius-architecture.html`（Archify 生成，showcase 质量，含明暗主题可交互浏览）。
- 项目规则：`.agent/rules/wpgenius-rules.md`（含 Ponytail 反过度设计准则与强制审核员闭环）。

## 更新日志

见 [CHANGELOG.md](CHANGELOG.md)。

## 许可证

MIT — 见 [LICENSE](LICENSE)。

## 作者

Sotary

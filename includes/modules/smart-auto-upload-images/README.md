# Smart Auto Upload Images 模块集成说明

## 概述

已成功将 `smart-auto-upload-images` 插件集成到 WP Genius 插件中作为一个模块。

## 集成内容

### 1. 核心功能（来自原插件）
- ✅ 自动检测并上传外部图片到媒体库
- ✅ 智能URL替换
- ✅ 支持自定义文件名模式
- ✅ 支持自定义Alt文本模式
- ✅ 图片尺寸限制
- ✅ 域名排除功能
- ✅ 文章类型控制
- ✅ 防止重复上传

### 2. WP Genius 增强功能
- ✅ **进度可视化UI** - 在保存文章时显示图片上传进度
- ✅ **自动设置封面** - 如果文章没有封面图，自动将第一张图片设置为封面
- ✅ **统一设置界面** - 集成到 WP Genius 的模块管理中

## 文件结构

```
includes/modules/smart-auto-upload-images/
├── module.php              # 模块主文件
├── settings.php            # 设置页面
├── progress-template.php   # 进度UI模板
└── progress-ui.js          # 进度UI脚本
```

## 使用方法

### 启用模块

1. 进入 **工具 → WP Genius Modules**
2. 找到 **Smart Auto Upload Images** 模块
3. 开启模块开关
4. 点击 **Configure Settings** 进入设置页面

### 配置选项

#### WP Genius 增强功能
- **自动设置封面图** - 启用后，如果文章没有封面图，会自动将第一张图片设置为封面
- **显示处理进度** - 启用后，保存文章时会显示图片上传进度的可视化界面

#### Smart Auto Upload Images 原生设置
更多高级设置（文件名模式、Alt文本模式、尺寸限制等）请访问：
**设置 → Smart Auto Upload Images**

## 工作流程

1. 用户在编辑器中粘贴包含外部图片的内容
2. 点击"发布"或"更新"按钮
3. 如果启用了进度UI，会显示处理进度对话框
4. 插件自动检测外部图片并上传到媒体库
5. 替换文章中的图片URL为本地URL
6. 如果启用了自动设置封面，且文章没有封面图，自动设置第一张图片为封面
7. 完成保存

## 与旧模块的关系

- **旧模块**: `auto-upload-images-module` 
- **新模块**: `smart-auto-upload-images`

启用新模块时，会自动禁用旧的 `auto-upload-images-module`，避免冲突。

## 技术细节

### 模块加载机制
新模块通过包装器加载原 `smart-auto-upload-images` 插件的核心功能：
- 加载插件的 autoloader
- 初始化插件容器
- 注册所有服务（ImageProcessor, ImageDownloader, Logger等）

### 进度监控
通过 AJAX 轮询机制实时获取上传进度：
- 前端每秒检查一次进度
- 后端通过 transient 存储进度信息
- 支持取消操作

### 自动设置封面
在 `save_post` hook 中：
1. 检查文章是否已有封面图
2. 如果没有，从文章内容中提取第一张图片
3. 查找对应的附件ID
4. 设置为文章封面

## 注意事项

1. **依赖关系**: 需要 `smart-auto-upload-images` 文件夹存在于插件根目录
2. **Composer**: 原插件需要运行过 `composer install`
3. **PHP版本**: 需要 PHP 8.0+
4. **WordPress版本**: 需要 WordPress 6.2+

## 故障排除

### 模块无法启用
- 检查 `smart-auto-upload-images` 文件夹是否存在
- 检查是否运行过 `composer install`
- 查看 PHP 错误日志

### 进度UI不显示
- 检查是否在设置中启用了"显示处理进度"
- 检查浏览器控制台是否有JavaScript错误
- 确认在编辑页面（post.php 或 post-new.php）

### 封面图未自动设置
- 检查是否在设置中启用了"自动设置封面图"
- 确认文章内容中有图片
- 确认图片已成功上传到媒体库

## 未来改进

- [ ] 批量处理现有文章的外部图片
- [ ] 更详细的进度信息（当前处理的图片预览）
- [ ] 失败图片的重试机制
- [ ] 处理完成后的详细报告

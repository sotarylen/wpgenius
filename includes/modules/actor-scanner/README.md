# Actor Scanner 模块 (WP Genius)

扫描文章与图册中的演员提及，自动创建/补全 Humans 分类条目，并从
[gfriends](https://github.com/gfriends/gfriends) 远程仓库获取演员头像与信息。

## 功能

1. **扫描文章内容**：解析文章标题 + 正文，识别其中提到的演员名。
   - 文中含日文名（假名）时**原文提取**（如「伊奈美いずな(伊奈美泉那)」）→ 与 gfriends 索引/现有术语精确匹配；
   - 文中仅中文名（简/繁）时 → 先匹配现有 Humans 术语名与昵称别名，再对 gfriends 做**模糊匹配**取相似度最高的候选；
   - 括号配对「日文名(中文名)」是最强信号，中文名自动锚定为日文名的别名。
2. **自动创建条目**：Humans 分类不存在该演员时自动创建（名称用 gfriends 日文名，role=演员，中文名存入 human_nickname）。
3. **获取演员信息**：从 gfriends 的 Filetree.json（远程，缓存 24h）获得演员日文名、所属公司、头像 URL。
4. **获取头像**：从 gfriends CDN 下载头像到媒体库并写入 human_avatar（仅当术语尚无头像时，不覆盖人工选择）。
5. **补全/丰富**：已存在条目自动补全 role（追加「演员」）、human_nickname（追加文中出现的别名）、缺失的头像。

## 匹配策略（无需字符映射表）

- **日文名优先**：文章中通常自带日文名（标题格式「日文名(中文名)」），直接精确匹配 gfriends 索引（3 万+ 演员）与现有 Humans 术语。
- **中文名猜测**：无日文名时，用「首字符索引 + bigram 相似度」在 gfriends 索引中模糊匹配，取得分最高的候选（如 三上悠亞 → 三上悠亜）。
- **噪音过滤**：句子片段（突然の相部屋）、助词（の/は/が）、AV 术语（中出し/デリヘル）、常见词（女優/新人）均被过滤。

## 使用方式

### 后台工具
WordPress 后台 → 工具 → WP Genius → Actor Scanner 标签页：
1. 点 **Prepare Index** 拉取 gfriends 索引（首次约数分钟，之后走缓存）；
2. 点 **Start Scan** 分批扫描（可配置内容类型、批量大小、是否创建新演员、是否仅扫未关联文章）。

### WP-CLI（推荐全量扫描）
```bash
# 全量扫描文章（每批 100 篇，创建新演员，下载头像）
wp w2p actor-scan scan --post-type=post --batch=100

# 同时扫描文章与图册
wp w2p actor-scan scan --post-type=post --post-type=albums

# 预览（不写入）
wp w2p actor-scan scan --post-type=post --dry-run

# 不创建新演员 / 不下载头像
wp w2p actor-scan scan --post-type=post --no-create --no-avatar

# 刷新 gfriends 索引 / 查看状态
wp w2p actor-scan refresh
wp w2p actor-scan status
```

## 数据源

- Filetree.json：`https://raw.githubusercontent.com/gfriends/gfriends/master/Filetree.json`（10 万+ 头像文件索引）
- 头像 CDN：`https://cdn.jsdelivr.net/gh/gfriends/gfriends@master/Content/`（优先）/ raw.githubusercontent.com（备用）
- 索引缓存：`wp-content/uploads/w2p-actor-scanner/gfriends-index.json`

> 站点运行在容器化环境（Docker DNS 解析到 198.18.x.x 保留网段）时，
> 模块已注册 `http_request_host_is_external` 白名单，允许下载 gfriends CDN 资源。

## 目录结构

```
includes/modules/actor-scanner/
├── module.php                          # 模块主类 + AJAX 控制器
├── options.php                         # CSF 设置页配置
├── views/tab-scanner.php               # 扫描工具界面
├── assets/js/actor-scanner.js          # 前端交互
└── includes/
    ├── class-gfriends-client.php       # gfriends 远程数据客户端
    ├── class-actor-matcher.php         # 演员名提取 + 匹配
    ├── class-actor-sync.php            # 术语创建/补全/头像下载
    └── class-actor-scanner-cli.php     # WP-CLI 命令
```

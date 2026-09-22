# 网盘媒体源模块 · 实施方案（草案，待确认）

> 状态：**待老雷确认，尚未写任何代码**
> 日期：2026-09-22

---

## 0. 立项依据

上一轮判断「不写插件、OpenList 一个就够」**已作废**。基于站点截图 + 数据库查证，实际体量：

| 项目 | 实测 |
|---|---|
| attachment（图片） | 1,114,611 |
| chapter（小说章节） | 308,586 |
| post（文章） | 42,011 |
| albums（图集） | 8,314 |
| novel（小说） | 828 |
| `wp-content/uploads` | **150 GB** |
| MinIO | 桶 `wp-media/{年份}/…` |
| 图集文件 | `uploads/albums/{post_id}/*.jpg` + `webp/*.webp` |

导航分区实际由 `category` 承担：Blogs 1837 / WordPress Plugins 1428 / WordPress Themes 369 / General Novels 346 / Albums 81 / Videos 39 / Adult Blogs 36392 / Adult Albums 8230 / Adult Novels 482 / Adult Videos 50。
分类法：`genre`（小说题材 15）、`photostudio`（工作室 54）、`humans`（演员 4294）、`post_tag`。

**关键发现**：图集标题形如 `[亞洲] 北野瑠華写真 [131P]`——方括号前缀为地区/来源、`[NP]` 为图片数，是**从命名解析出来的结构化元数据**。入库管道为 `smart-aui migrate-albums`：文件落到 `uploads/albums/` → CLI 批量注册进媒体库 → 站上成卡片。

**结论**：OpenList 只解决「文件怎么流出来」（管道层）；封面卡片流、结构化元数据、4294 演员 / 54 工作室的多维筛选、跨 111 万条统一搜索，它给不了（呈现层）。**插件该写，且它就是这个站的核心。**

### 需求重定义

> 给 wpgenius 增加一个**网盘媒体源**模块：让网盘文件能像现有本地附件一样进入同一套 category / ACF / 分类法体系，出现在卡片流里、能筛能搜、点开能播能下——**但文件本体不落服务器磁盘**。

---

## 1. 已确认前提

| # | 决定 |
|---|---|
| 范围 | 只接新资源，**不动 150 GB 存量** |
| 落位 | **混进现有 `category` 体系**，被同一套实时搜索/筛选命中 |
| 类型 | 杂（音视频/图片/软件/文档）→ **必须分批** |
| 基建 | OpenList 作管道层（部署文档：`/Users/sotary/dev/openlist/`） |
| 不做 | 独立分区 · 多用户/配额/商品化 · 自研网盘签名解析 |

---

## 2. 架构分层

```
网盘们  →  OpenList（管道层：统一接入 · 直链 · 流式代理 · 驱动维护）
              ↓
        wpgenius 网盘媒体源模块（呈现层：条目 · 封面 · 元数据）
              ↓
        站点现有体系（category / genre / humans / photostudio / ACF / 卡片流 / 搜索）
```

---

## 3. 核心设计：虚拟附件

### 3.1 为什么不能走标准附件机制

本仓库 grep 查证：**100+ 处代码假设「附件文件在本地」**。

| 文件 | 本地文件假设调用点 |
|---|---|
| `smart-aui/library/src/classes/Services/ImageDownloader.php` | 14 |
| `media-engine/includes/services/class-logger-service.php` | 10 |
| `media-engine/includes/services/class-audit-service.php` | 9 + 5 |
| `media-engine/includes/services/class-converter-service.php` | 9 + 1 |
| `smart-aui/includes/class-media-orphan-bind.php` | 5 + 2 |
| `media-engine/includes/services/class-metadata-service.php` | 3 + 2 |
| `frontend-enhancement`（lightbox / music-handler / music-meta / module） | 12+ |
| `accelerate/includes/class-image-cleanup.php` | 2 |
| `smart-aui/includes/class-media-find-posts-filter.php` | 2 |

**⚠️ 最危险的三处（会删数据）：**

1. `media-engine` 的 **audit-service** — 残留媒体审计
2. `smart-aui` 的 **media-orphan-bind** — 孤儿媒体绑定
3. `accelerate` 的 **image-cleanup** — 图片清理

这三处若把「网盘附件」判定为「文件丢失」，**可能直接把记录当垃圾清掉**。这是必须**第一优先**埋防护的地方。

### 3.2 方案

- 生成**标准 attachment 记录** → 能进媒体库、进卡片流、被搜索命中
- 打来源标记（meta）：
  - `_w2p_media_source` = `netdisk:xunlei` / `netdisk:baidu`
  - `_w2p_netdisk_path` = 该文件在 OpenList 内的路径
  - `_w2p_netdisk_thumb` = 本地缩略图相对路径
- `_wp_attached_file` 不指向本地真实文件，走约定占位（专用前缀，便于识别）
- 挂 filter 做重定向：`get_attached_file` / `wp_get_attachment_metadata` / `wp_get_attachment_url` / `wp_calculate_image_srcset`
- **集中式判定 helper**：`W2P_Netdisk_Source::is_netdisk( $post_id )`，在既有审计/清理/转换/校验服务里**统一排除**——绝不在每处散落 `if`

### 3.3 数据表与队列

- 新表 `w2p_netdisk_sources`：`provider` / OpenList 挂载路径 / 状态 / 最后验证时间
- 入库复用现有 `includes/class-task-queue.php`：限速、断点续传、可暂停

---

## 4. 封面/缩略图 —— 建议推翻你选的方案

你选了「用 OpenList 缩略图接口，WP 只存 URL」。**不建议，四条理由：**

1. **一次卡片流 = 十几次回源。** 列表页一页 16–20 张卡，每张封面都要 OpenList 回源网盘抓原图再压缩。迅雷/百度都有限流，页面会明显变慢甚至超时。
2. **单点故障扩散成全站故障。** 迅雷 cookie 过期、OpenList 容器重启/升级——任一个发生，**所有网盘资源的封面同时变破图**。
3. **与现有体验割裂。** 现在封面是 `uploads/albums/{id}/webp/*.webp`，本地秒开、永不失效。混进同一分区后，同一卡片流里两种封面并存。
4. **成本对比悬殊。** 本地存小图约 100–200 KB/条，1 万条 = **1–2 GB**，相对 150 GB 可忽略；且已决定不动存量，这点空间腾得出来。

### 建议改为

**OpenList 缩略图接口只当「生成工厂」**（入库时调一次），生成的小图落本地一份：

```
uploads/netdisk-thumbs/{provider}/{路径hash}.webp
```

前台一律走本地。收益：

- 卡片秒开，与现有图集体验一致
- OpenList 挂了 / 迅雷 cookie 过期 → **只影响新资源首次入库**，已入库的纹丝不动

---

## 5. 分批建议

你答「杂，什么都有」→ 必须分批，不能一次全上。

### 第一批（建议）：图片 / 图集

- 与现有 `albums` CPT + `uploads/albums/{id}/webp/` + `photostudio`/`humans` 分类法 **100% 同构**，可复用代码最多
- 不涉及播放器（播放器是另一个复杂子系统）
- 正好把「来源抽象 + 虚拟附件 + 封面策略」三件事验证透

### 第二批：音视频
播放器 + Range 拖进度 + 直链

### 第三批：软件 / 文档
最简，跳转 / 下载卡

---

## 6. 风险清单

| # | 风险 | 后果 | 拦截措施 |
|---|---|---|---|
| 1 | 审计/清理误删网盘附件 | **数据丢失** | 集中式排除 + 全量 dry-run 先行 |
| 2 | 网盘限流 | 入库慢/失败 | 队列限速 + 重试退避 + 断点续传 |
| 3 | 迅雷 cookie 过期 | 该源全挂 | 后台健康检查 + 明确告警 |
| 4 | 混入现有分区 = 动 111 万附件的库 | 影响面大 | 所有写操作先 dry-run、可回滚 |
| 5 | Mac 内存仅 8 GB | 拖垮整个环境 | OpenList 已限 512m；入库任务限并发 |
| 6 | 无备份就动库 | 不可逆 | **动库前先 DB dump + uploads 快照** |

---

## 7. 待老雷拍板

1. **封面策略**：确认改成「OpenList 生成 + 本地落小图」？
2. **第一批范围**：确认做「图片 / 图集」？
3. **动手前**：是否先做一次 DB dump + uploads 快照？

---

## 8. 明确不做

- 不动 150 GB 存量
- 不另开独立分区
- 不做多用户 / 配额 / 商品化
- 不自己解析网盘签名（交给 OpenList）

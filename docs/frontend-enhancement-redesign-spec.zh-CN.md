# WPGenius「前端增强」模组重构方案 — 需求规格与验收标准（模块级）

> 适用范围：实现代理按此开发，测试代理按此验收。以 includes/modules/frontend-enhancement/ 现状与既有能力线（Lightbox/Reader/Code Highlight/Video）为基准核对过。
> 前置事实（已核验）：LX Music 开放 API（v2.7.0+，HTTP 127.0.0.1:23330，接口清单见 §F5）与 lxmusic:// Scheme URL（v1.17.0+，含自动拉起通道）均以官方文档为准；WP 7.1 本机源码核验 wp-includes/ID3/ 存在（含 module.tag.id3v2.php），核心音频元数据解析位于 wp-admin/includes/media.php（wp_read_audio_metadata）。
> 本方案**取代**旧 docs/audio-player-redesign-spec.zh-CN.md（单功能稿，已作废标注，见该文件头部）。

---

## 0. 模组现状与重构基调

### 0.1 能力线盘点

| 能力线 | 状态 | 处置 | 理由 |
|---|---|---|---|
| Lightbox（含 Masonry） | ✅ 上线，用户满意基准 | **保留**，作为模组体验规范范本 | 按需加载/细粒度设置/config+i18n/反馈闭环 |
| Reader Mode | ✅ 上线 | 保留 | 书章节阅读有明确用户 |
| Code Highlight | ✅ 上线（默认关） | 保留，不扩展 | 低优先级存量功能 |
| Video Player | ✅ 上线但**存在缺陷** | **保留现状 + Tab 标记「实验性」**；新功能不再依赖其实现；模块级媒体内核（M4）上线后迁移 | 缺陷清单见 §0.2 |
| Audio/Music | ❌ 占位 | **重构主体**（本方案核心） | 需求：平台嵌入/自定义列表/上传提取 Tag与歌词（自动列表 V2 记档）/LX 本地联动 |

### 0.2 视频模块缺陷清单（用户指示：不复用）

| # | 缺陷 | 源码证据 |
|---|---|---|
| 1 | 全局键盘抢占（keyboard.global=true），与其它播放器/未来音频互抢 | video-optimizer.js L115-118 |
| 2 | JS 内联样式违规（style.aspectRatio），违反项目禁内联样式约束 | video-optimizer.js L141-144 |
| 3 | 「Play in Lightbox」仅 i18n 文案，无实际弹层接线 | module.php L367 + 全文无打开逻辑 |
| 4 | 进度/续播/倍速与 Plyr 深度耦合，无播放列表/下载等能力 | 全模块 |

> **处置边界（明确）**：「不复用视频模块」= 不复用其实现（含 Plyr 装配方式）；**不构成**对通用播放器库（APlayer）的禁令。内核选型见 §4。

## 1. 模组定位与用户分群

定位：**「内容消费体验增强套件」**——图像（Lightbox）、长文（Reader）、代码（Highlight）、媒体（Music 能力线）四大体验域，共享模组基建（按需加载、CSF Tab 设置、i18n、window.w2p 反馈、内容探测规范）。

**用户分群（显式说明，避免定位张力）**：
- **访客**：消费 P1（站内音乐资产/播放列表/歌词）、P2（平台嵌入）。
- **站长**：P3（LX Music 本地工具）+ 管理端（上传、建列表、Tag 维护）。PX 详见 §2。

## 2. 音乐能力线产品定义（三层结构）

| 层 | 能力 | 面向 | 可达性 |
|---|---|---|---|
| P1 站内音乐资产 | 上传/媒体库/自定义播放列表/Tag/歌词 | 访客 + 站长 | 全站 |
| P2 平台嵌入 | 网易云等歌单/单曲嵌入播放 | 访客 | 全站（受外链/版权限制） |
| P3 LX Music 联动 | 状态/控制/歌单推送/自动拉起 | **仅站长本机** | 127.0.0.1（仅站长浏览器可达；WP 在 Docker 容器内，PHP 侧不可达 localhost → 纯前端） |

**P3 定位声明**：站长本机效率工具（非访客特性）。默认仅登录管理员可见，避免将站长实时听歌信息（曲名/封面）广播给访客（隐私面）。前置条件：LX Music 端需在设置中开启「自定义接口」，且浏览器与 LX 同机——写入设置页 label 与本文档。

## 3. 五大功能规格（F1–F5）

### F1 平台嵌入（P2）
- 短代码 `[wpg_music platform="netease" type="playlist|song" id="xxx" auto="0|1"]`；V1 仅实现 netease 适配，qq/spotify 留适配器接口（不做）。
- 网易云 outchain iframe（playlist 高 450px / song 高 66px）；**type/id/height 的真实组合参数不在文档写死**，M1 用真实歌单/单曲实证后固化（外链/版权限制现状亦一并实证）。
- 外链失效（版权/网络）→ 优雅降级卡片（源名称+链接），不崩溃；卡片含「在 LX Music 打开歌单」按钮（仅对可见角色渲染，platform→source 映射 netease=wy）。
- 平台适配器接口（getEmbedUrl/getIdFromUrl/getFallback），V1 单实现，接口预留。

### F2 自定义播放列表（P1）
- CPT `wpg_playlist`（capability_type=post + map_meta_cap=true；管理端写入限有编辑权限角色，防低权用户经 meta 存储注入渲染于前台的 XSS）。
- 字段：标题/封面 URL（协议白名单 http/https）/描述/meta 有序曲目数组 [{audio_id,title,artist,url,cover,lyrics}]。
- 后台：媒体库筛选音频 + 批量添加 + 拖拽排序；前台 `[wpg_playlist id]`（id 一律 absint）→ 播放器 + 曲目列表（顺序/循环/随机、当前高亮、歌词面板）。
- 播放器形态 V1：**卡片 + 迷你条**；「全屏歌词」形态 V2（YAGNI 记档）。

### F3 上传与元数据提取（P1，走框架原生台阶）
- 挂载点：`wp_generate_attachment_metadata` filter（**非 add_attachment**），音频 MIME 白名单命中时执行。
- **DRY（M1 实证定案）**：核心在上传时**已同步解析**（wp_generate_attachment_metadata → wp_read_audio_metadata，wp-admin/includes/media.php:3714），title/artist/album/时长/封面（image blob）由 wp_add_id3_tag_data 写入 `_wp_attachment_metadata`。实现挂 **`wp_read_audio_metadata` filter**（WP≥6.1），在核心 filter 内直接取 `$data` 补 USLT 歌词（getID3 id3v2 USLT 帧解析已确认）——纯数组读取，**无需二次解析、无需异步**；仅当 M2 实测发现 USLT 未进核心 metadata 时直读 `$data['id3v2']['USLT']`。
- 封面（M2 冒烟实证：WP 7.1 核心**已原生实现**）：wp_generate_attachment_metadata 从 ID3 APIC 创建封面附件（attachment_thumbnail_args + `_cover_hash` 去重）并设为 `_thumbnail_id`（主题支持 attachment:audio 缩略图时）；插件**不再自建封面附件**（Ponytail 台阶 3 框架原生），渲染期直接 `get_post_thumbnail_id()`。
- V1 **不做自动归集**（上传后经后台手动入列表）；「按专辑/按批次自动生成列表」移 V2 记档。
- flac（M1 实证）：WP 7.1 `wp_get_mime_types` **已原生包含** flac → **删除 music_upload_flac 开关**（死开关，违反无僵尸开关约束）。

### F4 Tag 与歌词（P1）
- 读取：ID3v2 标题 TIT2/艺术家 TPE1/专辑 TALB/封面 APIC/歌词 USLT（经 §F3 通道入库 attachment meta）。
- 歌词来源（M3 已落地）：文件 USLT 与同名 .lrc 双源 → 独立 postmeta `wpg_audio_lyric`；`music_lyric_priority` 决定优先源（file=USLT 优先 / lrc=.lrc 优先，空时回退另一源）；.lrc 守卫：file_exists + is_readable + ≤64KB + UTF-8 归一（GBK→iconv）+ sanitize_textarea_field；无 → 占位文案；后台手动录入歌词编辑器 **V2**。
- 前端：LRC 解析器 + 同步滚动（当前行高亮/点击跳转）；歌词编码 UTF-8 LRC 常见防护（GBK 判定与提示）。
- **安全（双层防线）**：渲染侧 textContent（AC-M6 注入测试）；**写入侧 sanitize**——USLT/.lrc 属外部输入（上传文件内容），写入 attachment meta 前经 `sanitize_textarea_field` + `wp_kses_post`（与核心 wp_add_id3_tag_data 同级别清洗），封面 blob 转附件带幂等守卫（attachment meta 标记 `_wpg_cover_attachment_id`，防重复上传/重复生成）。

### F5 LX Music 本地联动（P3）
- 后端仅读配置（启用/端口/超时/可见角色），PHP 不做 localhost 代理（容器不可达 + 攻击面，保持纯前端）。
- 前端连接器 wpg-lx.js：
  - 状态：`GET /status`（filter 字段）+ `EventSource /subscribe-player-status`（SSE 实时，设置可关，官方建议 SSE 非轮询）；
  - 控制（V1 收敛集）：/play /pause /skip-next /skip-prev /seek /volume /mute（collect/uncollect/dislike 移 V2 记档）；
  - 场景联动：单曲「LX 播放」→ `lxmusic://music/play?data={name,singer,source,img,...}`（用 tag 字段，source 按平台映射）；嵌入歌单「LX 打开」→ `lxmusic://songlist/open/{source}/{id}`；
  - **自动拉起**：首次 fetch /status 失败/超时 → 状态条「LX Music 未运行」→ 用户点击「启动」→ `location.href="lxmusic://player/togglePlay"`（需用户手势）→ 2s 轮询 /status 至多 12 次，就绪自动恢复；**容忍系统协议确认弹窗引入的更长延迟（30s+ 上限）**；
  - 可见角色：默认**仅登录管理员**（非授权角色不渲染状态条与拉起按钮，AC 断言）。
- 安全：LX 返回文本一律 textContent；picUrl 入 <img> 前协议白名单（仅 http/https）。

## 4. 播放内核选型（R1 反转后主案）

| 方案 | 覆盖 | 结论 |
|---|---|---|
| **APlayer（MIT）主案** | 列表/连播/循环/随机/歌词(lrc)/迷你形态内建 | **V1 主案**；本地化 vendor（assets/lib/aplayer/，不经 CDN 强依赖） |
| 自研轻内核（原生 audio + 自研 UI）后备 | 同上需全量自建控件/队列/LRC | 仅当 M1 实证 APlayer 阻断需求（如与 LX 状态条冲突）时启动 |
| Plyr（视频同栈） | 不满足列表/歌词 | **禁用**（用户指示不复用视频模块实现） |

- M1 spike 决策门槛（≤3 人日）：APlayer 卡片+迷你+歌词+循环/随机四态与 LX 状态条共存无冲突 → 定主案；「全屏歌词」形态 APlayer 不内建，V1 裁掉（V2）。

## 5. 设置项设计（Music Tab，12 项，原 Audio Tab 重构；M1 实证删除 flac 开关）

| # | Key | 默认 | 说明 |
|---|---|---|---|
| 1 | music_enabled | true | 总开关；false=完全不加载（短代码原样输出/不 require/不入队） |
| 2 | music_player_mode | card | select：卡片 / 迷你（V2 增全屏歌词） |
| 3 | music_playlist_order | sequence | select：顺序 / 循环 / 随机 |
| 4 | music_embed_default_platform | netease | 嵌入默认平台（V1 仅 netease） |
| 5 | music_embed_autoplay | false | 嵌入 iframe 默认 auto |
| 6 | music_tag_extract | true | 上传时提取 ID3 tag（F3/F4 共享通道） |
| 7 | music_lyric_priority | file | select：文件 USLT（USLT 优先）/ 同名 .lrc（sidecar 优先，M3 已落地） |
| 8 | music_lx_enabled | false | LX 联动总开关（站长工具，默认关） |
| 9 | music_lx_port | 23330 | LX 端口 |
| 10 | music_lx_timeout | 2000 | 状态探测超时 ms |
| 11 | music_lx_sse | true | SSE 实时订阅（关=轮询 fallback） |
| 12 | music_lx_visible | admin | select：仅管理员（默认）/ 仅登录用户 / 全部 |

> 挂载语义：新 key 缺省按代码 fallback，与 CSF default 一致（对齐 smart-aui 规格先例）；已存旧 Audio Tab 值尊重。M1 首步核实 get_settings 默认值合并机制。

## 6. 模组架构与文件变更

| 文件 | 动作 | 说明 |
|---|---|---|
| options.php | 改 | Tabs 重组：Lightbox(含Masonry)/Reader/Code Highlight/Video(标记「实验性」)/**Music**（原 Audio Tab 重构，12 项） |
| module.php | 改 | 新增 music_defaults() 合并 + has_music_content()（M2 探测 [wpg_playlist]、core/audio、<audio>；[wpg_music] 随 M3 短代码注册）+ 入队分支 + init_music 挂载 |
| includes/class-music-handler.php | 新增 | 短代码渲染（M2 仅 [wpg_playlist]，id absint；渲染期按附件批量反查 URL/封面/歌词，缺失→降级卡片） |
| includes/class-music-meta.php | 新增 | wp_read_audio_metadata filter（static 接棒）+ wp_generate_attachment_metadata：USLT/unsynchronised_lyric→wpg_audio_lyric postmeta；封面由核心原生（_thumbnail_id + _cover_hash）；无二次解析 |
| includes/class-playlist-cpt.php | 新增 | wpg_playlist CPT（capability_type=post, map_meta_cap；show_in_menu=upload.php）+ 曲目 meta 存 attachment_id 数组 + save_post 守卫（autosave/revision/nonce/capability/wp_unslash）+ 后台媒体选择器/拖拽排序 |
| includes/class-audio-handler.php | **删除** | 空占位类（git 留痕），防僵尸代码 |
| views/music-player.php | 新增 | 播放器/列表/歌词模板（短代码输出） |
| assets/lib/aplayer/ | 新增 | APlayer 本地化 vendor（主案；经 M1 定案） |
| assets/js/wpg-music.js | 新增 | 装配/列表/嵌入/歌词 UI（对齐 lightbox/reader ES class 先例） |
| assets/js/wpg-lx.js | 新增 | LX 连接器（SSE/控制/拉起/轮询，仅可见角色入队） |
| assets/css/music.css | 新增 | wpg-music-* 前缀，复用 core.css 变量 |
| languages/wp-genius.pot | 改 | 重新生成 |

## 7. 验收标准（节选，完整 AC-1~AC-9）

- **AC-M1 卸载**：music_enabled=false → 不 require/不入队/短代码原样输出；**AC-M2 按需加载**：无音乐内容零资产。
- **AC-M3 F1**：短代码输出正确 iframe；失效场景降级卡片；「LX 打开歌单」仅对授权角色渲染。
- **AC-M4 F2**：建列表→前台播放/排序/循环/随机/当前高亮；**meta XSS 构造测试（title/lyrics 含 <script>、<img onerror> → 无注入）**；短代码 id absint 校验（非数字 → 空输出）。
- **AC-M5 F3**：上传 mp3 → 核心解析 title/artist/album + 歌词（unsynchronised_lyric）→ 插件落 wpg_audio_lyric postmeta（单一解析路径断言）；封面由核心建 _thumbnail_id（_cover_hash 去重）；flac 原生可上传。
- **AC-M6 F4**：USLT / 同名 .lrc（M3 已落地，按 priority 决策，空源回退）→ wpg_audio_lyric 同步滚动；**歌词/ .lrc 负载含 HTML → textContent 渲染无注入**；.lrc 超 64KB / 不可读 / 不存在 → 静默跳过；picUrl 非 http/https 不加载。
- **AC-M7 F5**：LX 运行=SSE 状态条实时；未运行=提示+点击拉起+轮询恢复（容忍 30s+）；**非授权角色不可见状态条/按钮**；控制集仅 play/pause/prev/next/seek/volume/mute。
- **AC-M8 安全/工程**：phpcs/node --check/i18n/无内联样式（沿用 video 违规先例防线写法）/375px 无溢出/APlayer 本地化无 CDN 强依赖。
- **AC-M9 共存**：music + lightbox + reader 同页无样式/事件冲突；LX 状态条与站内播放器互不干扰。

## 8. 边界与风险

| 风险 | 等级 | 缓解（M1 实证项以 ⚡ 标记） |
|---|---|---|
| ⚡ https 前台 fetch/SSE 至 http://127.0.0.1:23330（Chrome 私网访问/PNA 策略演进可能预检拦截） | 中 | M1 在真实部署（https 前台+本机 LX）验证；被拦则记录 workaround（用户站点设置 localhost 例外或降级轮询 status） |
| ⚡ EventSource 不支持自定义头 | 低 | LX 无鉴权，无碍 |
| ⚡ lxmusic:// 首次导航系统确认弹窗不可编程绕开 | 中 | 轮询恢复容忍 30s+ 延迟；失败给出重置文案 |
| ⚡ 网易云外链/版权现状与 type/id/height 参数 | 中 | M1 用真实歌单/单曲实证后再固化 |
| ⚡ WP 7.1 核心元数据解析覆盖范围（image 字段是否存在） | 低 | M1 实证；缺 APIC 走直连补 |
| 第三方库（APlayer）维护节奏慢 | 低 | 本地 vendor + 功能面窄；V1.1 评估自研迁移 |
| flac 版权与体积 | 低 | 原生支持；版权风险由内容政策承接，不做技术拦截 |
| 核心封面依赖主题支持（attachment:audio 缩略图） | 低 | 主题未声明则静默无封面（降级可接受）；handler 读取 _thumbnail_id 已 absint 保护 |
| 歌词编码 GBK 乱码 | 低 | 检测与提示 |

## 9. 里程碑（重估 16–20 人日上限）

| 里程碑 | 内容 | 预估 |
|---|---|---|
| M0 | 本规格冻结 + 审核员 APPROVE | 0.5d |
| M1 | 技术 spike（⚡ 项实证 + APlayer 定案 + getSettings merge 核实；server 侧项已完成，运行态项见 spike 报告） | ≤3d |
| M2 | 内核装配 + 播放列表 CPT + 上传/Tag/歌词 | 5–7d |
| M3 | 嵌入 F1 + LX 联动/拉起 F5 | 3–5d |
| M4 | 验收 AC-1~9 + 视频迁移同内核立项评估 | 2–3d |

---

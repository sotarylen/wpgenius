# M1 技术 Spike 报告 — 前端增强模组重构（音乐能力线）

> 状态：server 侧实证项**已完成**（本报告）；运行态（浏览器/容器）实证项给出**验收方法与脚本模板**，随 M2 在真实环境执行。
> 关联文档：docs/frontend-enhancement-redesign-spec.zh-CN.md（模块级规格，本报告为 M1 里程碑交付物，并已回写规格修订）。

---

## 1. WP 7.1 getID3 / 音频元数据能力 ✅（源码实证）

| 结论 | 证据 |
|---|---|
| WP 内置 getID3 可用 | wp-includes/ID3/getid3.php 存在；module.tag.id3v2.php（含 USLT L980 / APIC L1358 / SYLT L1027 帧解析）、module.tag.lyrics3.php、module.audio.mp3/flac/ogg 等齐备 |
| 核心已解析音频元数据 | wp-admin/includes/media.php:3714 `wp_read_audio_metadata()`：getID3 analyze → length/length_formatted/fileformat/filesize/mime_type/created_timestamp + `wp_add_id3_tag_data()`（id3v2/id3v1 comments 全量写入，经 wp_kses_post_deep 清洗；APIC/评论图片 → metadata[image] blob+mime+尺寸） |
| 核心上传时同步解析 | wp-admin/includes/image.php:590-593：wp_generate_attachment_metadata 对 audio/video MIME 调 wp_read_audio_metadata |
| filter 增强可行（≥6.1） | wp_read_audio_metadata 末尾有 `apply_filters( wp_read_audio_metadata, $metadata, $file, $file_format, $data )`，**$data 为完整 getID3 分析结果**——挂此 filter 取 USLT 歌词 / 封面为零成本（无二次 analyze） |
| USLT 歌词 | getID3 id3v2 模块解析 USLT 帧（frame_name==USLT → parsedFrame[data]）；是否自动并入 comments[lyrics] 待 M2 容器实测，fallback 直读 $data[id3v2][USLT] |
| flac MIME **已原生支持**（推翻原假设） | wp-includes/functions.php:3534 `flac => audio/flac` → **music_upload_flac 开关删除**（规格已回写） |

### 1.1 对本方案的三处修正（已回写规格文档）

1. **挂载点与异步设计**：F3 由「wp_generate_attachment_metadata + wp_schedule_single_event 异步」改为「挂 wp_read_audio_metadata filter」，核心已同步解析，filter 内仅为数组读取，**无需异步、无二次解析**（DRY 最大化）。
2. **封面策略**：核心 metadata[image] 是二进制 blob → 上传后转存为标准图片附件（wp_upload_bits + wp_insert_attachment），获得 URL + 自动缩略图，媒体库可见；非必需文件不生成。
3. **flac**：原生支持 → 删除设置开关（12 项）。

## 2. getSettings 默认值合并 ⚠️（机制缺位，需补模块级合并）

| 结论 | 证据 |
|---|---|
| 当前无 defaults 合并 | class-abstract-module.php:72 get_settings() = `get_option( w2p_settings, [] )` 原样返回；CFS 仅在保存设置页时把 default 写库 |
| 影响 | 全新安装或从未保存新字段时 key 缺失 → 「未保存 key 按 default」语义不成立 |
| 定案 | Music 处理层新增静态 `music_defaults()` 映射（12 key 单处维护，与 CSF default 断言一致），在模块 get_settings() 覆盖层合并；**不动全局**（对齐最小 Diff） |

## 3. 网易云 outchain 嵌入 ✅（可嵌入）

| 项目 | 结果 |
|---|---|
| 歌单 3778678（热歌榜） | HTTP/2 200，text/html，5673B 壳页 |
| 单曲 347230（海阔天空） | HTTP/2 200，同尺寸壳页（内容由 JS 按 id 加载） |
| 嵌入可行性 | **无 X-Frame-Options、无 frame-ancestors 限制**（CSP 仅 upgrade-insecure-requests）→ iframe 可嵌入 ✅ |
| 待 M2 浏览器实测 | 歌单/单曲 type 语义差异、版权受限歌曲表现（决定降级卡片触发阈值） |

## 4. 播放内核 APlayer 定案 ✅（主案成立，自研后备不启动）

built dist v1.10.1（59KB min）实证包含：`lrcType`（歌词）、`mini`、`fixed`（迷你/固定条）、`listFolded`/`listMaxHeight`（列表折叠/上限）、`mutex`（**独占播放内建**）、`order`（list/random）、`autoplay`、`theme`、`volume`。

| 需求 | APlayer 覆盖 |
|---|---|
| 播放列表 + 顺序/随机 | ✅ order + audio[] |
| LRC 同步歌词 | ✅ lrcType=3（歌词内容逐条） |
| 卡片 + 迷你形态 | ✅ mini:true / fixed:true |
| 独占播放 | ✅ mutex 内建 |
| 全屏歌词形态 | ⭕ 不内建 → V2 自研 overlay（维持记档） |

定案：assets/lib/aplayer/ 本地 vendor 1.10.1（MIT，不经 CDN 强依赖）；loop 模式（all/one/none）在 M2 冒烟验证。

## 5. LX Music 开放 API：CORS ✅ 浏览器直连可行

源码 src/main/modules/openApi/index.ts（GitHub master）实证：

- 常规响应头：`Access-Control-Allow-Origin: *`（L10 sendResponse）；SSE 响应同（L69）→ **跨源 fetch / EventSource 可读响应**（无需鉴权、无预检 GET）。
- `httpServer.listen( port, ip )`（L233），端口可配置（默认 23330）；接口集与官方文档一致（/status /lyric /lyric-all /subscribe-player-status /play /pause /skip-next /skip-prev /seek /volume /mute /collect /uncollect）。

### 5.1 剩余运行态风险（M2 浏览器实测项，非阻塞）

| 风险 | 处置 |
|---|---|
| https 页面 → http://127.0.0.1 混合内容 | loopback 属 potentially-trustworthy，主流浏览器通常放行；M2 在本站（https://web.sotarylen.com）实测 Chrome fetch/SSE |
| Chrome PNA（Local Network Access）未来收紧 public→loopback | 当前默认放行；若被拦：设置页提示 + 状态条降级「手动刷新」 |
| lxmusic:// 首启系统确认弹窗不可编程绕开 | 已计入 30s+ 轮询容忍；失败给重置文案 |

## 6. M2 运行态实证脚本模板（部署环境执行）

### 6.1 容器（php_wp）— USLT/APIC 实测

```sh
docker exec php_wp sh -c 'cd web && wp eval '
  "$file = WP_CONTENT_DIR . '/uploads/2026/09/sample.mp3';"
  "$id3 = new getID3();"
  "$d = $id3->analyze($file);"
  "var_export($d['id3v2']['USLT'] ?? null); // 歌词帧"
  "var_export($d['comments']['lyrics'] ?? null); // 是否并入 comments"
  "var_export($d['id3v2']['APIC'][0]['data'] ?? null); // 封面 blob"
'
```

### 6.2 浏览器（站长本机，https://web.sotarylen.com 前台）

```js
// 1) CORS/混合内容探针（控制台）
fetch("http://127.0.0.1:23330/status").then(r=>r.json()).then(console.log).catch(console.error);
const es = new EventSource("http://127.0.0.1:23330/subscribe-player-status");
es.onmessage = e => console.log("SSE:", e.data);
// 2) 拉起链（点击按钮后执行）
location.href = "lxmusic://player/togglePlay";
// 3) 恢复轮询
setInterval(() => fetch("http://127.0.0.1:23330/status").then(r=>r.ok && clearInterval(...)), 2000);
```

### 6.3 网易云嵌入（浏览器）

```html
<iframe src="https://music.163.com/outchain/player?type=2&id=3778678&auto=0&height=450"></iframe>
<iframe src="https://music.163.com/outchain/player?type=2&id=347230&auto=0&height=66"></iframe>
```

> 分别验证：歌单列表渲染、单曲播放、版权受限歌曲的降级表现（用于降级卡片阈值）。

## 7. 决策汇总（供 M2 实施）

| # | 决策 | 影响 |
|---|---|---|
| 1 | APlayer 1.10.1 本地 vendor 为主案 | 内核定案，自研后备不启动 |
| 2 | 挂 wp_read_audio_metadata filter 提取歌词/封面 | 无二次解析、无异步；F3/F4 合并为同一通道 |
| 3 | 封面 blob 转存附件（幂等守卫：meta 标记防重复生成） | 获得 URL/缩略图；不生成无封面附件 |
| 4 | 删除 music_upload_flac 设置（原生支持） | 12 项设置；AC-M5 改「默认可上传」 |
| 5 | 模块级 music_defaults() 合并 | 满足「未保存 key 按 default」语义，不动全局 |
| 6 | 网易云 outchain iframe 直接嵌入 | 无 X-Frame 限制；降级卡片阈值待浏览器实测 |
| 7 | LX 浏览器直连（CORS 已实证） | 纯前端成立；PNA/混合内容列入 M2 实测 |

## 8. 残余风险跟踪

| 项 | 状态 | 责任人时机 |
|---|---|---|
| USLT 是否并入 comments[lyrics] | 待 M2 容器实测 | 实现 class-music-meta 前 |
| 网易云版权限制降级表现 | 待 M2 浏览器实测 | F1 降级卡片阈值 |
| PNA/混合内容拦截（若有） | 待 M2 浏览器实测 | 设置页提示 + 降级方案 |
| lxmusic:// 拉起成功链路 | 待 M2 本机实测 | F5 轮询恢复实现 |
| APlayer loop 模式冒烟 | 待 M2 | 播放列表循环语义 |

---
---

## 9. M2 冒烟实证增补（改写 2 个旧结论）

- **歌词键名**：WP 内置 getID3 将 USLT 文本写入 comments['unsynchronised_lyric']（USLT 帧解析后主动 unset data），核心 wp_add_id3_tag_data 自动进 metadata → capture 主源改为 `$metadata['unsynchronised_lyric']`（回退链保留）。
- **封面**：WP 7.1 核心在 wp_generate_attachment_metadata 内**原生**创建封面附件（attachment_thumbnail_args 过滤 + `_cover_hash` 去重 + `_thumbnail_id`，主题支持 attachment:audio 时）——插件删除自建封面逻辑，渲染期用 `get_post_thumbnail_id()`（实证：三张不同附件共享同一封面附件 1674974）。
- **环境注意**：本站 Advanced_Media_Offloader 会在处理后移走本地文件（get_attached_file 失效），ID3 提取只保证发生在 upload/再生成时机；渲染期不依赖本地文件（URL/封面/歌词均来自 DB/附件体系）。

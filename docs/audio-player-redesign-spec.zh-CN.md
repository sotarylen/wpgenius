> # ⚠️ 已作废（DEPRECATED）
>
> 本文件为**单功能稿**，已被模块级方案 **docs/frontend-enhancement-redesign-spec.zh-CN.md** 取代（含范围变更：音乐能力线 F1–F5、内核选型反转、LX Music 集成）。保留仅为审计留痕，**实现与验收一律以模块级方案为准**。

---

# （作废存档）WPGenius「前端增强」Audio Player 产品重构方案 — 需求规格与验收标准

> 适用范围：实现代理按此开发，测试代理按此验收。凡钩子名、option key、白名单、默认值语义均以 includes/modules/frontend-enhancement/（options.php / module.php / includes/class-*.php / assets/js|css/）现状与既有模块（Lightbox、Video、Reader、Code Highlight）为基准核对过。
> 产品基准：**Lightbox**（用户指定为满意基准）。本方案将该模组的成熟度模型（按需加载、12+ 细粒度可开关设置、config+i18n 注入、前端 ES class、toast/confirm 复用、内容探测）平移到 Audio Player。
> 目标环境：WordPress 6.x/7.x 前台单篇（singular）页面，主题需允许插件的 the_content 过滤与资产注入。

---

## 0. 现状与差距（探索源码结论）

### 0.1 模块五个功能现状

| 功能 | Key | 状态 | 技术方案 | 成熟度参照 |
|---|---|---|---|---|
| Lightbox | lightbox_enabled | ✅ 已上线（用户满意，作基准） | 自研 ES class + AJAX | 13 项设置、按需加载、权限分级 |
| Video Player | video_enabled | ✅ 已上线 | Plyr CDN + 自研增强 JS | 续播/倍速/独占/autoplay 预防 |
| Reader Mode | reader_enabled | ✅ 已上线 | 自研工具栏 JS | 主题/字号/章节导航 |
| Code Highlight | code_highlight_enabled | ✅ 已上线（默认关） | Prism 本地化 | 语言探测/主题/行号/复制 |
| **Audio Player** | audio_enabled | ❌ 占位 | 无 | 2 个死开关 + Coming Soon notice |

### 0.2 Audio 现状（占位痕迹）

- options.php Tab5（L355-381）：仅 `_notice_audio_coming_soon` notice + `audio_enabled`（默认 false）+ `audio_custom_player`（默认 false），均为死开关。
- module.php：`init_audio_player()`（L468-470）空方法；`enqueue_frontend_assets()`（L228-260）无音频分支；**无 has_audio_content() 内容探测函数**（对比 has_image_content/has_video_content 均有）。
- includes/class-audio-handler.php：27 行空占位类（已在 novel-manager vendor composer classmap 中注册 autoload，可被直接实例化）。

### 0.3 与 Lightbox 基准的差距

| 维度 | Lightbox（基准） | Audio 现状 |
|---|---|---|
| 内容探测 + 按需加载 | has_image_content() + is_singular 门控 | 无探测、无入队 |
| 设置项 | 13 项，全带默认值与 label | 2 个死开关 |
| 前端类 | WPGeniusLightbox（977 行，健壮初始化/主题冲突拦截） | 无 |
| 后端 Handler | WPG_Lightbox_Handler 解析 + 模块内 AJAX（nonce+能力校验） | 空类 |
| i18n | wp_localize_script 注入全量 | 仅 2 条占位文案 |
| 反馈系统 | window.w2p.toast / confirm（随 w2p-core-css 前台可用） | 无 |

---

## 1. 需求规格

### 1.1 产品定位

把 Audio 从「占位小功能」升级为与 Lightbox 同级的体验模块，命名不变（仍为 Tab「Audio Player」），产品主张：

> **站点内所有音频（单曲 / 播客 / 有声书章节）获得统一、专业、可续播的播放体验**——同一套播放器、同一套续播/倍速/音量记忆状态、与视频播放器同技术栈、可逐项开关。

设计原则（对齐项目规则与 Ponytail 阶梯）：

1. **复用优先**：播放器内核直接复用 Video 模块已引入的 **Plyr**（Plyr 原生一等支持 audio）；进度续播/独占播放/autoplay 预防全部镜像 video-optimizer.js 既有实现模式。
2. **零侵入**：不写 AJAX、不改 post_content 本体（纯前端增强 + the_content 包装）；开关全生命周期在模块设置内闭环。
3. **按需加载**：仅 singular 且内容含可识别音频时才入队资产（对齐 lightbox/video/reader 门控）。
4. **全量 i18n**：所有可见字符串英文源串 + textdomain wp-genius（对齐 rules：禁硬编码非英文可见文本）。
5. **无僵尸开关**：每个开关都映射到明确的代码分支，CSF dependency 隐藏不生效的组合。

### 1.2 目标用户与场景

**听众（前台）**

- 场景 A：单曲/音频块（课程、访谈、音乐）——文章内一段音频，期望美观一致 + 倍速 + 断点续听。
- 场景 B：多段音频同一页（播客系列/分章试听）——期望互不干扰（独占播放）、各自续播。
- 场景 C（V2）：有声书章节——站点已有 novel/chapter CPT 与 Reader 模组，跨章节续听是自然延伸。

**站长（后台）**

- 一键开启/关闭，不侵入已有内容；格式白名单控制；版权保守默认（下载按钮默认关）。

### 1.3 功能范围

**V1 MVP（本次交付，对齐 Lightbox 深度）**

| # | 功能 | 说明 | 复用来源 |
|---|---|---|---|
| 1 | 自定义播放器 | Plyr 替换 <audio>（core/audio block、[audio] 短代码、裸 <audio>），控件随设置裁剪 | Plyr（视频同 handle） |
| 2 | 元信息展示 | 封面 + 标题（附件封面回退链，见 1.6） | 服务端注入 data-* |
| 3 | 进度续播 | localStorage（key 前缀 wpg_audio_progress_），播完清除，seek 时序见 3.6 | video-optimizer.js getVideoId/saveProgress 模式 |
| 4 | 倍速 0.5–2x | Plyr settings 菜单，依赖高级控件 | video speed 配置 |
| 5 | 音量记忆 | localStorage | 新增（沿用 wpg_reader_settings 持久化手法） |
| 6 | 独占播放 | 同页音频互斥；同页音视频互斥（捕获期暂停其它 media 元素） | video exclusive 语义扩展 |
| 7 | 下载按钮 | 默认关（版权） | Plyr download |
| 8 | 格式白名单 | 服务端包装判定与 JS 初始化共用同一判定（服务端注入 data-wpg-audio 属性，JS 只信任该属性 → 单一事实源，防漂移） | video_supported_formats 模式 |
| 9 | 自动播放预防 | 移除 autoplay 属性；**custom_player=false 时依然生效**（内容卫生，非播放器功能） | video_autoplay_prevention |
| 10 | 高级控件开关 | 精简 vs 完整控件集 | Plyr controls 数组装配 |

**V2（Roadmap，YAGNI 排除项记档，勿在 V1 实现）**

1. `[playlist]` 短代码深度增强（曲目列表 + 连播 + 当前高亮）。
2. 全局迷你浮动播放器（跨页面续听，与 Reader 章节结构联动）。
3. 外部平台（SoundCloud/Spotify oEmbed iframe）「Lightbox 播放」——**前置依赖**：Video 模组的 lightbox 播放当前仅 i18n 文案（module.php L367，无实际弹层接线），V2 需先评估并统一打通。
4. 播放统计事件钩子（window 派发 wpg:audio:* 事件供 GA/UM 等接入）。

### 1.4 设置项设计（Tab5 全量重构，11 项）

> 挂载语义（全 Tab 统一，对齐 smart-aui 规格文档 1.1）：开 = 挂载生效；关 = 完全不加载（PHP 不 require、不挂 hook、JS/CSS 不入队）。**新 key 缺省时按代码内 fallback 生效，与 CSF 字段 default 一致**：全新安装按 default；已保存过旧 Tab5（audio_enabled=false 已持久化）的站点**尊重已存值**，升级不翻改用户选择（审核 R1 落地）。

| # | Key | 中文标题 | 类型 | 默认 | 依赖（CSF dependency） | 说明 |
|---|---|---|---|---|---|---|
| 1 | audio_enabled | 启用音频播放器 | switcher | true | — | 总开关；false = 功能完全卸载（handler 不 require、无入队、无包装） |
| 2 | audio_custom_player | 使用自定义播放器 | switcher | true | — | false = 完全走浏览器原生 <audio>，除 #9 外全部子功能失效（CSF 隐藏） |
| 3 | audio_show_metadata | 显示封面与标题 | switcher | true | custom_player==true | 封面+标题合并（审核 R6 落地，同源同注入路径） |
| 4 | audio_resume_progress | 断点续播 | switcher | true | custom_player==true | localStorage 续播，播完清除 |
| 5 | audio_speed_control | 倍速控制 | switcher | true | custom_player==true 且 advanced_controls==true | speed 在 Plyr settings 菜单内，精简模式不可达故隐藏 |
| 6 | audio_remember_volume | 记忆音量 | switcher | true | custom_player==true | |
| 7 | audio_exclusive_playback | 独占播放 | switcher | true | custom_player==true | 同页音/视频互斥 |
| 8 | audio_download_button | 显示下载按钮 | switcher | false | custom_player==true | 版权保守 |
| 9 | audio_autoplay_prevention | 防止自动播放 | switcher | true | —（不依赖 custom_player） | 服务端移除 autoplay 属性；custom_player=false 时亦生效 |
| 10 | audio_supported_formats | 支持的音频格式 | text | mp3,wav,ogg,oga,m4a,aac,flac,opus,weba | — | 逗号分隔扩展名白名单，服务端判定唯一来源 |
| 11 | audio_show_advanced_controls | 显示高级控件 | switcher | true | custom_player==true | true=play/progress/time/mute/volume/settings(speed)/download；false=play/progress/time |

> 移除旧 `_notice_audio_coming_soon` notice；「Coming Soon」文案全部移除（Changelog 中声明正式上线与默认值语义，审核 R1 落地）。
>
> **M1 首步**：核实 get_settings() 层「未保存 key 按 CSF default 合并」机制存在（参照 smart-aui 规格 1.1 先例）；若缺先补统一 merge，否则上方 fallback 语义不成立（审核建议 2）。

### 1.5 开关 → 代码路径映射（防僵尸开关，审核 R3 落地）

| 开关 | custom=false 时 | 代码路径 |
|---|---|---|
| audio_enabled=false | — | module.php init() 不 require class-audio-handler.php、不入队任何资产 |
| audio_custom_player=false | 内容卫生项仍生效 | handler 不执行包装（the_content 原样），JS 不入队；audio_autoplay_prevention 服务端移除 autoplay 属性仍执行 |
| 其余子开关（3–8、11） | 隐藏（CSF dependency）且无效 | 仅在 custom_player=true 分支被读取 |
| audio_speed_control | 隐藏 | 仅在 advanced_controls=true 时装配进 Plyr settings |

### 1.6 封面来源回退链（审核 R2 落地）

the_content 阶段 core/audio 的附件 id（block 注释）已不可得，只剩 src URL。回退链：

1. `attachment_url_to_postid( $src )`（**static 数组缓存**，多音频页只查一次库/URL）；
2. 命中附件 → 取媒体库封面：`wp_get_attachment_metadata` 的 `image` 字段 → `wp_get_attachment_image_src( thumbnail )`；
3. 未命中 → 零查询、不显示封面占位（纯文字标题或仅播放器）。

标题优先取 `wp_get_attachment_metadata` 的 `title` 字段，次取文件名 basename。注入使用 `esc_attr`，JS 渲染用 createElement/textContent（**禁止 innerHTML 拼接复杂 HTML**，对齐 rules 负向约束）。注意：`metadata['image']` 为 getID3 二进制 blob（非 URL），实现时忽略 blob、仅用 `wp_get_attachment_image_src` 缩略图，不内联 data URI（审核建议 1）。

### 1.7 资产自足与去重（审核 R4 落地）

- `enqueue_audio_assets()` **自身** enqueue：`plyr-css` / `plyr-js`（CDN 3.7.8，handle/版本号/依赖数组与 enqueue_video_assets() **逐字节一致**，保证同页与 Video 去重）、`w2p-core-css`、`w2p-admin-ui`（toast/confirm 依赖）、`wpg-audio-player`（新 css）、`wpg-audio-player-js`（新 js，依赖含 jquery / plyr-js / w2p-admin-ui）、wp_localize_script 注入 `wpgAudioConfig`（settings + i18n）。
- 新增资产路径（审核 R7 落地）：
  - `includes/modules/frontend-enhancement/assets/js/audio-player.js`
  - `includes/modules/frontend-enhancement/assets/css/audio-player.css`

### 1.8 服务端包装规则（class-audio-handler.php 实现要点）

- the_content 过滤，仅 `is_singular()` + `audio_enabled` 时执行（对齐 video-handler 守卫）；`audio_custom_player` 仅决定「包装 + data-* 注入 + JS 入队」三件事，autoplay 移除与其它内容卫生不依赖它（审核后置 A 修正）。
- 目标：`<audio[^>]*>.*?</audio>` 及其 `<source>`；兼容 core/audio 渲染出的 `<figure class="wp-block-audio">` 包裹（**不得剥除 figure 类**）。
- 判定：src 扩展名 ∈ 白名单（basename 去 query/fragment 后判断）→ 注入 `data-wpg-audio="true"` + `data-wpg-audio-src` +（可选）`data-wpg-audio-cover` / `data-wpg-audio-title`，外包 `<div class="wpg-audio-wrapper" data-wpg-audio-wrapper="true">`；白名单外 → 原样透传（AC-10）。
- **不**剥除 `controls` 属性：无 JS 时原生回退可用（JS 缺载兜底，对齐鲁棒性）。
- `audio_autoplay_prevention` 与 custom_player 无关，直接 preg_replace 移除 autoplay 属性（对齐 video-handler L42-44 模式）。
- 已包装检测（strpos wpg-audio-wrapper）防重复过滤（对齐 video wrap_video 幂等）。
- 作用域排除：位于 `.wp-playlist` 作用域内的 <audio> 一律不包装（V1 不增强 [playlist]，防破坏 wp-playlist.js 行为，审核后置 B 修正）。

### 1.9 前端 JS 设计（WPGeniusAudioPlayer 类）

- 收集：`document.querySelectorAll([data-wpg-audio="true"])`（单一事实源：只信服务端注入的属性）。
- 初始化：`new Plyr(el, {...})`；controls 按高级控件/下载/倍速设置装配；`keyboard: { global: false }`（避免与 Video 的 `keyboard.global:true` 同页互抢方向键，审核 R5 落地）；`speed` 选项 [0.5,0.75,1,1.25,1.5,1.75,2]。
- 状态持久化：`wpg_audio_progress_<id>`（id 取 src basename，对齐 getVideoId）；音量 `wpg_audio_volume`。
- 独占播放：play 时 document 捕获期遍历 audio,video 暂停其它（审核 R5：与视频跨类型互斥，纯前端实现，无需改 video 模块）；**Caveat**：视频 Plyr 有全局键盘，音频勿开全局键盘。
- 续播时序（边界 3.6）：loadedmetadata 后 seek → 立即 save 一次；timeupdate 2s 节流 save；**自然 ended 才清除**（手动拖到结尾不算，用 ended 事件 + 判别非 seek 触发）。
- 错误：audio error 事件 → 保留原生元素降级提示（console.warn + 可选 toast，JS 禁内联样式，一律 class 切换）。
- 主题冲突：不拦截全局点击（与 Lightbox 不同，无需）；样式全部 .wpg-audio-* 前缀隔离。

### 1.10 建议文件布局与命名

| 文件 | 动作 | 说明 |
|---|---|---|
| includes/modules/frontend-enhancement/options.php | 改 | Tab5 全量重构（1.4 表），移除 Coming Soon notice |
| includes/modules/frontend-enhancement/module.php | 改 | 新增 has_audio_content()（V1 探测仅：core/audio block、[audio] 短代码、裸 <audio>；[playlist]/wp-playlist 属 V2 不探测）；enqueue_frontend_assets() 增加音频分支；完成 init_audio_player()（require + new WPG_Audio_Handler($settings)） |
| includes/modules/frontend-enhancement/includes/class-audio-handler.php | 改 | 实现 1.8 全部逻辑（构造入参 settings，对齐 WPG_Video_Handler/WPG_Masonry_Handler 模式） |
| includes/modules/frontend-enhancement/assets/js/audio-player.js | 新增 | WPGeniusAudioPlayer 类（1.9） |
| includes/modules/frontend-enhancement/assets/css/audio-player.css | 新增 | .wpg-audio-* 差异化样式，复用 core.css 设计变量（--w2p-*） |
| languages/wp-genius.pot | 改 | 重新生成，纳入新英文源串 |

> 类统一前缀 WPG_（服务端）与 wpg-（CSS/属性）；JS 类名 WPGeniusAudioPlayer，窗口实例 window.wpgAudioPlayer（对齐 window.wpgLightbox / wpgVideoOptimizer）；配置对象 wpgAudioConfig（对齐 wpgLightboxConfig / wpgVideoConfig）。

---

## 2. 验收标准

### 功能开关与卸载（AC-1.x）

- **AC-1.1** audio_enabled=false：不 require class-audio-handler.php、无任何 audio 资产入队（network 无 audio-player.js/css、无 plyr 因音频加载）、the_content 无任何包装残留。预期：与功能未开发时行为一致（完全卸载，对齐 smart-aui AC-4.6 语义）。
- **AC-1.2** audio_enabled=true 但页面无音频内容（无 core/audio、无 [audio]、无 <audio> tag）：不加载任何音频资产（对齐 has_image_content/has_video_content 门控）。预期：列表页/无音频单篇零开销。
- **AC-1.3** audio_custom_player=false + audio_enabled=true：无包装、无 audio-player.js 入队；页面 <audio> 为原生控件；若原内容含 autoplay 属性 → 该属性被移除（#9 独立生效）。预期：子开关全部失效且 CSF 界面隐藏（dependency）。

### 自定义播放器（AC-2.x）

- **AC-2.1** core/audio block（figure.wp-block-audio）：渲染为 Plyr 自定义播放器，浏览器默认控件不可见；figure 类保留、布局不破。
- **AC-2.2** 精简控件模式（advanced=false）：仅 play/progress/current-time/duration 可见；mute/volume/settings 不可见。
- **AC-2.3** 白名单外格式（如 .exe、.mp3?sign=x 的 query 伪装、.mkv）：不包装、不初始化 Plyr，保持原生 <audio>。预期：AC-10 语义。
- **AC-2.4** 同页 Video + Audio 均启用：网络请求中 plyr.css / plyr.polyfilled.js **仅各 1 次**；两播放器均正常（AC-12 去重凭证）。

### 状态持久化（AC-3.x）

- **AC-3.1** 播放至 3:00 刷新页面：续播位置 ∈ [2:50, 3:10]（±10s，loadedmetadata 后 seek）。
- **AC-3.2** 播放到结尾（自然 ended）：localStorage 记录被清除；再刷新从头播放。
- **AC-3.3** 手动拖进度到 99% 处停止/刷新：记录保留（非自然 ended 不清除）。
- **AC-3.4** 音量调至 30% 刷新：恢复 30%（音量记忆）。
- **AC-3.5** localStorage 不可用（隐私模式/禁用）：功能静默降级，无 JS 错误（try/catch 全包裹）。

### 播放行为（AC-4.x）

- **AC-4.1** 倍速：含 0.5/1/1.5/2 档位，切换生效且进度显示同步。
- **AC-4.2** 独占播放：同页两段音频，播放第二段时第一段暂停；同页音频+视频，播放视频时音频暂停、反之亦然。
- **AC-4.3** 下载按钮：switch 开 → 控件含 download；关 → 无 download（且 Plyr download 配置不注入）。
- **AC-4.4** 封面标题：有附件封面 → 显示封面+标题；无附件 → 无占位、零 DB 查询（attachment_url_to_postid 静态缓存短路）、无封面相关额外网络请求（审核建议 3 措辞修正）。

### 内容与兼容（AC-5.x）

- **AC-5.1** [audio] 短代码（WP 渲染含 source[type]）：正常包装（src 判定用 <audio> src 或首个 <source> src）。
- **AC-5.2** 音频 404：Plyr 初始化失败 → 页面无白屏，原生 <audio> 可见，console 有告警。
- **AC-5.3** 移动端 375px：播放器无横向溢出，控件可点。
- **AC-5.4** 无障碍：所有按钮含 title/aria-label；键盘可聚焦操作（focus 态下方向键/空格可用，非全局）。
- **AC-5.5** 多段音频页面性能：每段音频 preload 尊重原属性（preload=none 不被改写）；init 只处理 data-wpg-audio 元素。

### 工程（AC-6.x）

- **AC-6.1** php -l 全部通过；phpcs（WPCS，phpcs.xml.dist）无新增报错；JS node --check 通过。
- **AC-6.2** 所有可见字符串英文源串 + textdomain wp-genius；languages/wp-genius.pot 重新生成包含新串。
- **AC-6.3** 无内联 style 属性（JS 中禁 style.xxx 赋值，全部 class 切换）；无 innerHTML 复杂拼接；无未使用的僵尸代码/开关。
- **AC-6.4** data-* 注入均 esc_attr；无新增非安全 SQL/请求（V1 无 AJAX 端点，审计确认零服务端写路径）。

---

## 3. 边界情况

- **3.1 无 controls 属性的 <audio>**：作者明确隐藏控件 → 不包装（保持原语义）。
- **3.2 已配置 preload=none**：保持，Plyr 按 metadata 轻载；续播 seek 依赖 loadedmetadata 时序（3.6）。
- **3.3 URL 带 query/fragment**：白名单判定取 path 的 basename 扩展名（如 /a.mp3?t=1 → mp3）；仅当 path 无扩展名时不包装。
- **3.4 多个 <source>**：取首个合法 source 判定；无 src 的 <audio>（纯 JS 填充型）不包装。
- **3.5 第三方 oEmbed（SoundCloud/Spotify iframe）**：V1 不处理（V2 规划），iframe 不受影响。
- **3.6 续播时序**：seek 必须在 loadedmetadata 之后（duration 有效）；seek 成功立即 saveProgress 一次；ended 清除仅在「非手动 seek 至结尾」时，即监听 ended 事件由播放自然完成触发（对齐 video L192-197 行为）。
- **3.7 同页音视频键盘冲突**：audio Plyr keyboard.global=false；Video 已占用全局键（video-optimizer.js L115-118），音频不得再开全局键（审核 R5）。
- **3.8 内联样式复发防线**：video-optimizer.js L141-144 使用了 style.aspectRatio（违规先例）；audio-player.js 一律 class 切换，AC-6.3 代码评审确认。
- **3.9 主题 double-filter**：the_content 多次过滤场景，包装幂等（已含 wpg-audio-wrapper 检测）。
- **3.10 存量站点升级**：已存 audio_enabled=false 尊重旧值（Changelog 声明语义），不强制翻改；新站按 default true 生效。
- **3.11 [playlist] 作用域**：V1 不探测 [playlist]/wp-playlist（仅含 playlist 的页面零加载）；混排页（既有 core/audio 又有 playlist）包装时显式排除 .wp-playlist 内的 <audio>；其深度增强（曲目列表/连播/高亮）属 V2 roadmap #1（审核后置 B 修正）。

---

## 4. 里程碑与风险

### 里程碑

| 里程碑 | 内容 | 预估 |
|---|---|---|
| M0 需求冻结 | 本规格 + 审核员 APPROVE 放行 | 0.5d |
| M1 V1 实现 | options/module/handler/JS/CSS/pot | 5–7d |
| M2 验收 | AC-1.x ~ AC-6.x 全绿 + 审核员复评 | 1–2d |
| M3 V2 立项评估 | playlist / 浮动播放器 / 外部平台(含 Video lightbox 接线) / 统计钩子 | 单独立项 |

### 风险

| 风险 | 等级 | 缓解 |
|---|---|---|
| Plyr CDN 不可达（与 Video 同源风险） | 中 | 与 Video 同 CDN 版本；失败时原生 <audio> 兜底（AC-5.2） |
| 主题对 <audio>/figure 的既有样式冲突 | 低 | .wpg-audio-* 前缀 + 覆写走 CSS 变量；验收含主题环境对照 |
| 存量用户无感知的「默认值落差」（曾关过 Audio） | 低 | Changelog 声明 + 升级说明；尊重已存值（3.10） |
| 与 Video 全局键盘/独占逻辑互扰 | 中 | keyboard.global=false + 捕获期统一 pause（AC-4.2 / 3.7） |

---
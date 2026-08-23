# WPGenius Smart AUI「媒体增强」Tab — 需求规格与验收标准

> 适用范围：实现代理与测试代理照此开发/验证。所有钩子名、option key、白名单与既有代码行为均以子主题 Impreza-child/functions.php（[任务1][任务3] 段）与 inc/media-bulk-move-category.php、插件 includes/modules/smart-aui/ 现状为准核对过。
> 目标环境：WordPress 6.x/7.x 后台 upload.php 列表模式。
> 真实测试数据：孤儿附件 350330（core 文件名 282c808f1a8bb884d797195a33db7044）→ 文章 326494，其内容含远程 URL https://image.playno1.com/images/2022/11/10/282c808f1a8bb884d797195a33db7044.gif（路径与本地不一致）。

---

## 1. 需求规格

### 1.1 Tab 结构（CSF）

- 文件：includes/modules/smart-aui/options.php，在 smart_aui_tabs（type tabbed）的 tabs 数组中，于「Smart AUI Settings」之后、「Capture Failure Logs」之前**插入**第三个 Tab：
  - title = __( '媒体增强', 'wp-genius' )
  - icon  = 'fa fa-images'
  - fields 按功能分组（subheading + switcher），末尾一条 submessage 承载迁移说明。
- 存储说明：新字段 key 落在 w2p_settings.smart_aui_tabs 内；class-settings.php::get_settings() 已做扁平化，module.php 可直接读取，无需额外处理。
- **挂载语义（全 Tab 统一）**：开 = 挂载生效；关 = 完全不加载（PHP 不 require、不挂 hook、JS 不入队）。实现于 module.php::init() 中逐开关判断；**读取兜底默认值必须与 CSF 字段 default 一致**——已保存过旧设置（smart_aui_tabs 里没有新 key）或全新安装时，未保存字段按代码内 fallback 生效（迁移功能按 true、enhance_attach 按 false），不得因 key 缺失而全部静默关闭。

### 1.2 四个开关规格

| # | Key | 中文标题 | 类型 | 默认 | 默认值理由 |
|---|-----|---------|------|------|-----------|
| 1 | smart_aui_media_find_posts_filter | 附加筛选 | CSF switcher | true | 子主题当前已启用（保持现状行为）；仅后台弹窗 UI + 查询改写，不改内容，风险低 |
| 2 | smart_aui_media_bulk_move_category | 批量移动到分类 | CSF switcher | true | 子主题当前已启用（保持现状行为）；仅用户显式触发才改动 taxonomy，风险可控 |
| 3 | smart_aui_media_mime_cache | 媒体库性能优化 | CSF switcher | true | 子主题当前已启用（保持现状行为）；纯性能短路，三级缓存有正确性兜底 |
| 4 | smart_aui_enhance_attach | 文件名反查与 URL 回写 | CSF switcher | false（维持插件现状） | 会**改写文章 post_content**（URL 回写），爆炸半径大；插件内该开关现状即默认关，迁移 Tab 不改变用户既有选择 |

> 说明：功能 1–3 的默认 true 只在「未保存过任何值」时生效；老用户已保存过设置则按已存值，符合迁移最小惊扰原则。功能 4 的 key 保持不变（避免破坏已存设置与 class-settings.php 中 smart_aui_enhance_attach → enhance_attach 的 legacy 映射），仅把它的 switcher 从 Settings Tab 迁入新 Tab。

### 1.3 开关文案（title / label 建议，翻译域统一 wp-genius）

- **附加筛选**（smart_aui_media_find_posts_filter）
  - label：在「附加到文章或页面」弹窗注入 Type / ID 范围 / Status 三档筛选；查询白名单 post / page / novel / albums，固定排除 chapter，搜索只匹配文章标题（不查内容），每页 25 条。
- **批量移动到分类**（smart_aui_media_bulk_move_category）
  - label：媒体库列表模式批量操作新增「移动到分类」，选择目标分类（us_media_category）后批量替换附件分类，并提示移动结果。
- **媒体库性能优化**（smart_aui_media_mime_cache）
  - label：短路 get_available_post_mime_types 慢查询（约百万附件下表扫描 ~20s），Redis → wp_options → SQL 三级缓存 + 双写，TTL 12h；CLI 提供 media-mime-flush / media-mime-test / media-perf-verify。
- **文件名反查与 URL 回写**（smart_aui_enhance_attach，沿用现有文案）
  - label：媒体库列表 Unattached 过滤下，弹窗自动按附件文件名反查包含该图（内容中）的文章；选择后经 wp_media_attach_action 把文章里匹配文件名的图片 URL（含远程遗留 URL）替换为本地 URL。

**并存提示（4 个开关 label 末尾统一追加）**：
> 与子主题同名功能并存时以先加载者生效；迁移完成后请删除子主题对应代码（functions.php 的 [任务1][任务3] 段与 inc/media-bulk-move-category.php）。

### 1.4 建议文件布局与命名

- includes/modules/smart-aui/includes/class-media-find-posts-filter.php（功能 1：pre_get_posts/posts_where 后端 + admin_print_footer_scripts UI 注入 + JS）
- includes/modules/smart-aui/includes/class-media-bulk-move-category.php（功能 2：bulk_actions-upload / handle_bulk_actions-upload / admin_footer / admin_notices）
- includes/modules/smart-aui/includes/class-media-mime-cache.php（功能 3：pre_get_available_post_mime_types + flush + 3 个 CLI 命令注册）
- includes/modules/smart-aui/includes/class-attach-enhance.php（功能 4：**现有文件，不动实现**，仅挂载条件复用同一 key）
- JS：assets/js/smart-aui-media-enhance.js（功能 1 的筛选 UI 注入与 Search 劫持）与 assets/js/smart-aui-attach-enhance.js（功能 4，现有）
- 类前缀统一 W2P_SmartAUI_；**不得定义与子主题同名的全局函数**（w2p_filter_find_posts_query、w2p_force_title_only、w2p_short_circuit_mime_types、w2p_register_cli_commands、w2p_cli_media_mime_*、w2p_do_move_media_to_category 等），否则并存期 PHP Fatal「Cannot redeclare」。
- CLI 注册：class-media-mime-cache.php 内 cli_init 回调；回调开头先做并存守卫（见 3.6），已存在同名命令则跳过自身注册并 WP_CLI::warning。

### 1.5 legacy 同步

- class-settings.php 的 sync map **仅保留** smart_aui_enhance_attach → enhance_attach（已有）。
- 功能 1–3 的 key **不加入** legacy smart_aui_settings 映射：该 option 供 smart-auto-upload-images 库消费，三个功能与库无耦合；写入反而产生无消费方的死数据。

---

## 2. 验收标准

### 功能 1 附加筛选（AC-1.x）

- **AC-1.1** 开关开：upload.php 列表模式 → 任一附件行「附加到文章或页面」→ 弹窗搜索条下方出现 Type / ID / Status 三档筛选 UI（Type 四档：All / Posts / Albums / Novels；ID 占位符「100-500 或 12,34」；Status 四档：Default / Published / Draft / All），含隐藏 ac_exclude_chapter=1。**预期**：三档控件可见且可操作。
- **AC-1.2** Type 筛选：选 Posts 点 Search → 结果仅 post 类型；选 Novels → 仅 novel；选 All → 仅 post / page / novel / albums 四类，**永不出现 chapter、attachment、wp_template、wp_block**。**预期**：类型列与筛选一致。
- **AC-1.3** ID 范围：输入 100-500 → 仅返回 ID ∈ [100,500]（含端点）；输入 12,34 → 仅返回 ID 12 与 34；输入 abc 或 100-50（起点>终点）→ **该条件被忽略**（等价无 ID 过滤），其余筛选照常生效。**预期**：结果集与条件语义一致。
- **AC-1.4** Status 筛选：选 Published → 仅 publish 状态；选 Draft → 仅 draft；默认 Default（any）。**预期**：状态列与筛选一致。
- **AC-1.5** title-only 语义：构造一条「关键词只在 post_content、标题不含」的文章 → 搜索该词**不返回**；把关键词放进标题 → **返回**。**预期**：搜索只匹配 post_title。
- **AC-1.6** 交互边界：任意筛选组合下结果每页 ≤ 25 条；Search 走自定义 AJAX（页面不刷新、结果注入 #find-posts-response）；**Select 按钮保持原生 findPosts.update**——选中文章后弹窗关闭、附件关联成功（post_parent 更新）。**预期**：Search 与 Select 职责不混。

### 功能 2 批量移动到分类（AC-2.x）

- **AC-2.1** 列表模式 upload.php → 批量操作下拉出现「移动到分类」选项；切换到网格模式 → **该选项不出现**。**预期**：仅列表模式生效。
- **AC-2.2** 选择「移动到分类」→ 下拉旁出现「目标分类」下拉（us_media_category，层级缩进）；未选分类直接点「应用」→ 红色错误提示「请先选择目标分类」，无任何附件被改动。**预期**：nocat 分支提示。
- **AC-2.3** 勾选 2 个附件（含 1 个已有其它 us_media_category term 的附件）+ 选目标分类 + 应用 → 提示「已成功将 2 个媒体文件移动到目标分类」；**预期**：两个附件的该 taxonomy term 被**替换**为目标分类（原 term 被移除，非追加）。
- **AC-2.4** 媒体库按 us_media_category 过滤 → 刚移动的附件出现在目标分类下。**预期**：term 已持久化。
- **AC-2.5** 混选非附件对象或无编辑权限对象 → 仅可处理项被移动，提示数量 = 实际成功数；0 成功 → warning 提示「没有媒体文件被移动」。**预期**：逐项 edit_post 权限 + post_type=attachment 校验生效。
- **AC-2.6** 目标分类被删除后再次提交（badcat 路径）→ 提示「目标分类无效，操作已取消」。**预期**：get_term 校验拦截。

### 功能 3 媒体库性能优化（AC-3.x）

- **AC-3.1** 开关开 → 首次调用 get_available_post_mime_types('attachment') 走一次 DISTINCT SQL，随后 Redis 与 option **双写**；再调用 → 命中缓存不再发 SQL（Redis HIT 无日志，option HIT 有 [w2p-mime] HIT wp_option 日志）。**预期**：观察 error_log / debug 日志确认三级链路。
- **AC-3.2** wp media-mime-flush → **Redis 与 option 双路清空**（option w2p_media_mime_types、w2p_media_mime_types_ts + Redis w2p_media_mime_types_v2、_ts，group w2p）→ 输出 success；再请求触发重建。**预期**：flush 后首个请求为 MISS 并重建双写。
- **AC-3.3** wp media-mime-test → 输出 {cached: x.xxx} 与 {uncached: x.xxx}（ms）；--flush 先清缓存再测。**预期**：cached 显著小于 uncached（数量级差距）。
- **AC-3.4** wp media-perf-verify（新增）→ 逐项输出：① pre_get_available_post_mime_types 是否挂载（has_filter）；② option / Redis 缓存存在性与 age（<12h）；③ 与子主题并存检测（function_exists 子主题函数名 → 提示并存与删除指引）；④ 可选缓存 vs SQL 耗时对比。**预期**：全部项目有明确 PASS/FAIL 输出。
- **AC-3.5** 仅对 type === 'attachment' 介入；对其它类型透传 $null 不短路。**预期**：非附件类型查询行为与原生一致。
- **AC-3.6** 新增/删除附件**不**自动打掉缓存（保持 C 方案 auto-flush 禁用），缓存可经 CLI / 手动 w2p_flush_mime_cache() 强制失效或 12h 自然过期。**预期**：批量上传期间缓存稳定不被反复清空。

### 功能 4 文件名反查与 URL 回写（AC-4.x，复用真实数据）

- **AC-4.1** 列表模式 + Unattached 过滤 → 孤儿附件 **350330** 行「附加到文章或页面」→ 弹窗打开时自动按 core 文件名 282c808f1a8bb884d797195a33db7044 反查，结果列表**默认包含文章 326494**（该文件名只在内容中，标题不含）。**预期**：弹窗首屏即有命中，无需手动输入。
- **AC-4.2** 选择 326494 → Select → 附件 post_parent=326494；**预期**：文章内容中 https://image.playno1.com/images/2022/11/10/282c808f1a8bb884d797195a33db7044.gif 被替换为本地 wp_get_attachment_url(350330)（src 命中、内容保存成功、数据可核对）。
- **AC-4.3** 附件本地 URL 带 -2026-08-23（Smart AUI 日期后缀）、-150x150（尺寸）、-scaled 后缀时，仍能匹配文章里无后缀的原始文件名并替换。**预期**：get_core_filename 宽松匹配生效。
- **AC-4.4** 只替换 <img src>；非 img 引用（CSS url()、短代码属性）不替换；已替换过的文章再次附加同一附件 → 无重复写库（内容无变化则跳过 wp_update_post）。**预期**：替换范围收敛、幂等。
- **AC-4.5** detach 动作不触发回写；AJAX 无权限 / 无效附件 ID → 返回 error 且不注入任何内容。**预期**：仅 attach 动作、仅合法请求生效。
- **AC-4.6** 开关关：class-attach-enhance.php 不加载，无 hook、无 JS 注入、无 AJAX 端点（network 面板无 w2p_smart_aui_find_posts_by_filename 请求）。**预期**：功能完全卸载。

---

## 3. 边界情况

### 3.1 网格 vs 列表模式
- 功能 1/2/4 仅列表模式：功能 1 的注入 JS 检查 #find-posts 存在且仅在 upload.php + wp_script_is('media') 时注入；功能 2 的 JS 以 .bulkactions 存在与否判定（网格模式无该容器自动跳过）；功能 4 的 enqueue 检查 mode=list（含用户偏好 get_user_option('media_library_mode')）。网格模式验收：**三功能均无报错、无残留 UI、无多余请求**。
- 功能 3 与显示模式无关：后台任意调用点（含网格模式媒体库下拉）都会命中短路，属预期。

### 3.2 chapter 始终排除
- 后端白名单固定 post/page/novel/albums；array_diff 剔除 chapter（无论前端是否传 ac_exclude_chapter）；白名单被清空时回退到完整白名单（绝不让查询返回空 post_type）。前端 Type 下拉是静态四档，与后端白名单硬编码对齐，不依赖 get_post_types()（避免拉入 wp_template 等内部类型与漏掉 novel/albums 这类非 public 类型）。

### 3.3 ID 范围格式
- 100-500：正则 ^\s*(\d+)\s*-\s*(\d+)\s*$，要求 start ≤ end，含端点（range()）。
- 12,34：逗号分隔（允许空格），逐项 absint、剔除 0；单值 12 也命中该分支。
- 非法（abc、100-50、-1）→ 忽略该条件，**不清空** Type/Status 等其它筛选。
- 解析失败保持空数组 → 不设置 post__in（等价无 ID 过滤）。

### 3.4 title-only 语义 vs 文件名反查的差异（易混淆点，须写进文档）

| 维度 | 附加筛选（功能 1） | 文件名反查（功能 4） |
|------|--------------------|--------------------|
| 触发 | 用户在弹窗点 Search（action=find_posts） | 打开弹窗时自动调用（action=w2p_smart_aui_find_posts_by_filename） |
| 搜索字段 | **post_title 仅**（posts_where 注入 LIKE） | **post_content**（文件名通常在内容中） |
| 后端实现 | pre_get_posts 改写原生查询 | 独立 AJAX 端点，返回与原生兼容的 HTML 表 |
| 互扰 | 无：两个 action 不互相触发；子主题 title-only 只拦 action=find_posts，拦不住独立端点 | 同左 |

- 预期行为提示：用户在弹窗**手动输入文件名**搜索（走功能 1 路径）搜不到「只在内容里」的文章——这是 title-only 的设计语义，不是 bug；要按文件名反查应依赖功能 4 的自动行为。此差异需在 switch label 或帮助文案中一句带过，避免用户误报。

### 3.5 mime 缓存 TTL 与失效方式
- TTL：Redis wp_cache_set(…, 'w2p', 12*HOUR_IN_SECONDS)；option 侧以时间戳窗口 (time()-ts) < 12h 判有效。
- 失效方式：① CLI wp media-mime-flush；② admin 手动调 w2p_flush_mime_cache()；③ 12h 自然过期。
- **已知改进点（相对子主题）**：子主题的 wp media-mime-flush 只删 option、不删 Redis key，会导致 web 上下文继续命中陈旧 Redis。插件版本必须让 CLI flush 与 w2p_flush_mime_cache() 行为一致——**双路全清**。
- auto-flush（add_attachment/delete_attachment）保持禁用（C 方案），避免批量上传/扫描反复打掉缓存（曾引发 30s 超时现场）。

### 3.6 CLI 命令在并存期的重复注册（关键风险）
- WP-CLI 的 WP_CLI::add_command() 对已注册同名命令**直接抛异常**；而 cli_init 在每次 wp 命令执行时都会触发 → 并存期若子主题与插件都注册 media-mime-flush，**任何 wp 命令都会报「command already registered」而不可用**。
- 时序：cli_init 触发时主题 functions.php 已加载、子主题函数已声明。因此插件侧 CLI 注册回调**必须先做守卫**：若 function_exists('w2p_register_cli_commands') 或 WP_CLI::has_command('media-mime-flush') 命中 → **插件跳过自身注册**并 WP_CLI::warning('检测到子主题同名命令，请删除子主题对应代码')。删除子主题代码后插件恢复注册。验收时在并存期与删除后各跑一次 wp media-mime-test 验证可用。

### 3.7 子主题代码未删除时的行为说明（逐功能）
- **功能 1**：pre_get_posts / posts_where 双份回调 → 查询改写幂等（结果一致），但 LIKE 子句可能重复、日志翻倍；UI 注入双方都调用 → **JS 必须复用同一全局幂等标志 window.w2pFindPostsInjected**（子主题已用），后注入方直接 return，避免三档筛选 UI 出现两份。
- **功能 2**：bulk_actions-upload 数组同 key → 自动去重只显示一项；handle_bulk_actions-upload 双份执行同一替换 → 结果一致（幂等）；admin_footer 双份注入下拉 → JS 需检查 .w2p-move-cat-wrap 已存在则跳过（或复用同一 DOM 判断）。
- **功能 3**：pre_get_available_post_mime_types 链式短路——先注册者先返回缓存值，后注册者收到非 null 直接透传 → 结果一致、仅重复执行与重复日志；**CLI 是唯一真正会坏的环节**（见 3.6，插件必须让位）。
- **功能 4**：子主题无对应实现，无并存问题。
- 总原则：并存期功能可用（插件方做幂等与让位），但**必须**在开关 label、CLI warning、admin notice 三处提示用户删除子主题代码；删除后复验（见第 4 节）。

---

## 4. 迁移 / 删除计划

### 4.1 建议验证顺序（三阶段）

**阶段 A — 插件开开关，子主题代码保留（并存验证）**
1. 备份子主题：cp functions.php functions.php.bak.$(date +%Y%m%d%H%M)（站内已有同款备份惯例）。
2. 更新插件 → Smart AUI 设置出现「媒体增强」Tab；确认 1–3 开关默认开、4 保持关。
3. 回归功能 1/2/4 的列表模式 AC（功能 4 若原开关是关则先手动开，用真实数据 350330 → 326494）。
4. 功能 3 先**不做 CLI 验证**（并存期 CLI 由子主题注册，插件已让位）；仅验证后台媒体库页加载正常、无重复注入、日志无异常。
5. 确认 admin 出现并存提示（若实现）。

**阶段 B — 删除子主题对应代码**
1. 从 functions.php 删除 [任务1]（mime 缓存 + CLI 段，约 304–476 行）与 [任务3]（find_posts 增强段，约 477–771 行）。
2. 删除 require_once get_stylesheet_directory() . '/inc/media-bulk-move-category.php'; 行与 inc/media-bulk-move-category.php 文件。
3. 保留 functions.php.bak.* 至少一个备份版本。

**阶段 C — 复验（子主题代码已删）**
1. 重跑功能 1/2/4 全部 AC。
2. 跑功能 3 全部 AC，含 wp media-mime-flush / media-mime-test / media-perf-verify（此时插件注册恢复，verify 的并存检测应输出「无并存」）。
3. 网格模式回归（三功能无副作用）；媒体库页（百万附件）加载耗时观察（mime 命中不再发慢查询）。
4. 开关关→开往返一次，确认「关=完全卸载、开=重新挂载」。

### 4.2 给用户的操作指引文案（可直接复制）

> **WPGenius「媒体增强」迁移说明**
> Smart AUI 设置已新增「媒体增强」Tab：附加筛选、批量移动到分类、媒体库性能优化三项默认开启（与子主题现有行为一致），文件名反查与 URL 回写保持关闭（需手动开启，因为它会改写文章内容）。
> 迁移期间如子主题 Impreza-child 的旧代码仍在：同名功能以先加载者生效，界面不会重复，但 CLI 命令可能冲突——请按以下步骤清理：
> 1. 先在本页开启/核对各开关，并在媒体库列表模式完成一轮功能验证；
> 2. 备份并编辑子主题 functions.php，删除 [任务1] 媒体库性能优化段与 [任务3] 附加筛选段，以及末尾对 inc/media-bulk-move-category.php 的 require（连同该文件一起删除）；
> 3. 回到本页确认无并存提示，再执行 wp media-mime-flush、wp media-mime-test、wp media-perf-verify 复验性能优化；
> 4. 若日后无需某功能，关闭对应开关即可完全卸载该功能（不影响其它开关）。

---

## 5. QA 审查清单（供代码审查代理逐项勾选）

### 5.1 安全
- [ ] AJAX 端点全部 check_ajax_referer（nonce 独立，如 w2p_smart_aui_media_enhance）+ current_user_can('upload_files')（功能 4 已按此模式，功能 1/2/4 统一）。
- [ ] $_POST/$_GET 一律 sanitize_key / sanitize_text_field / wp_unslash + 白名单校验（post_type、post_status、ID 范围正则）；禁止裸拼接进 SQL。
- [ ] 功能 2 的 term 校验 get_term( $id, W2P_MEDIA_TAX )（防跨 taxonomy 注入）；功能 1 的 LIKE 用 $wpdb->esc_like + prepare。
- [ ] 输出转义：注入 HTML/JS 用 esc_html / esc_attr / wp_json_encode；admin_notices 文案不 echo 未转义的用户输入。
- [ ] URL 回写只允许 <img src 匹配、只写 post_content 字段，不触碰 post_content_filtered、不触发无限钩子循环（回写内 wp_update_post 不再次触发同类回写）。

### 5.2 命名与作用域
- [ ] 无与子主题同名的全局函数（见 1.4 名单）；新逻辑全部类方法或带 function_exists 守卫。
- [ ] key 命名：smart_aui_media_* 前缀；option/Redis key 复用子主题既有名（w2p_media_mime_types*，group w2p）保证迁移期缓存共享、不冷启动双份。
- [ ] hook 作用域：功能 1 仅 is_admin() && DOING_AJAX && action==='find_posts'；功能 2/4 仅 upload.php 列表模式；功能 3 仅 type==='attachment'。禁止影响前台、编辑页、其它 AJAX。
- [ ] 一次性过滤器（posts_where）执行后自移除，不得污染同进程后续查询（沿用 _w2p_title_only 标记 + remove_filter 模式）。
- [ ] JS 幂等：复用 window.w2pFindPostsInjected（功能 1）与 DOM 存在性判断（功能 2），并存期不重复注入。

### 5.3 回退与容错
- [ ] mime 三级缓存任一环失败可降级（Redis 挂 → option；option 过期 → SQL），SQL 结果双写回两级；返回类型恒为数组。
- [ ] 读取兜底默认值与 CSF default 一致（未保存设置时按 1–3 开 / 4 关生效）。
- [ ] ID 范围解析失败、term 无效、无权限对象：静默忽略或跳过，不整批报错、不中断。
- [ ] CLI flush 双路全清（含 Redis）——修正子主题只清 option 的缺口；media-perf-verify 全部自检项有明确 PASS/FAIL。

### 5.4 并存与冲突
- [ ] CLI 注册守卫：function_exists('w2p_register_cli_commands') / WP_CLI::has_command 命中即让位 + warning（并存期任何 wp 命令不得因重复注册而不可用）。
- [ ] 与插件内其它模块（如 media-engine）无同名 hook / option 冲突（pre_get_available_post_mime_types 仅本功能使用）。
- [ ] admin notice 并存提示可关闭（is-dismissible）且仅对管理员显示。

### 5.5 其它
- [ ] i18n：所有文案 __(…, 'wp-genius')，含 %d 计数用 sprintf + 翻译注释。
- [ ] 性能：功能 1 每页 25 条上限；功能 2 逐项 wp_set_object_terms 无 N+1 之外的新增查询（接受既有实现）；功能 3 命中路径零 SQL。
- [ ] 数据安全/可恢复：URL 回写前可备份原内容（QA 建议在 rewrite 处留日志或 filter 钩子供回滚）；批量移动 term 前可打印受影响附件清单。
- [ ] 卸载/清理：uninstall.php 维持现状（不新增删除 w2p_media_mime_types*——与子主题共享，删除会触发重建慢查询；子主题代码删除后再由站点侧自行清理）。
- [ ] 缓存破坏：新增/改动 JS 以 filemtime 作 ?ver=（沿用 class-ui.php 既有做法），避免改 JS 后浏览器命中旧缓存。

---

## 附：挂载逻辑参考（module.php::init() 追加段）

    // 功能 1：附加筛选
    $s = $this->get_settings(); // 已扁平化 smart_aui_tabs
    if ( ! empty( $s['smart_aui_media_find_posts_filter'] ) ) {
        require_once __DIR__ . '/includes/class-media-find-posts-filter.php';
        new W2P_SmartAUI_Media_FindPosts_Filter();
    }
    // 功能 2：批量移动到分类
    if ( ! empty( $s['smart_aui_media_bulk_move_category'] ) ) {
        require_once __DIR__ . '/includes/class-media-bulk-move-category.php';
        new W2P_SmartAUI_Media_Bulk_Move_Category();
    }
    // 功能 3：媒体库性能优化
    if ( ! empty( $s['smart_aui_media_mime_cache'] ) ) {
        require_once __DIR__ . '/includes/class-media-mime-cache.php';
        new W2P_SmartAUI_Media_Mime_Cache();
    }
    // 功能 4：文件名反查与 URL 回写（现有，key 不变）
    if ( ! empty( $s['smart_aui_enhance_attach'] ) ) {
        require_once __DIR__ . '/includes/class-attach-enhance.php';
        new W2P_SmartAUI_Attach_Enhance();
    }

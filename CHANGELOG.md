# Changelog

所有重要变更都会记录在此文件中。

格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## [Unreleased] - 2.1 积攒中

### 新增 (Added)

- **Novel Manager 书籍统计（章节数 / 字数）**：新增 `W2P_Novel_Stats`，按 `related_novel_id` 聚合 chapter 并把结果回写到 novel 的 `count-chapters` / `count-words`（书籍字段组 `group_6045c262f41f5`）——
  - chapter 新增只读自定义字段 `word-count`（local field group `group_w2p_chapter_stats`），单章字数在看版可见且不可手改
  - 三种触发方式：chapter 保存/回收/删除/改挂时自动刷新（脏队列 + shutdown 统一汇总，避免批量导入逐章重算）、文档导入自然覆盖、后台「Statistics」页签手动校准
  - 后台「Statistics」页签：全站分批回填（游标深分页、进度条、日志、可中断续跑）、单本搜索重算、覆盖率概览
  - 每小时计划任务兜底刷新未完成的脏队列；模组停用时自动补跑并清理调度
- **Novel Manager 修复交互升级**：新增「按小说检索/预览/单本应用」流程 ——
  - 小说搜索（支持 ID 精准定位 + 标题模糊匹配，附章节数与完成状态）
  - 单书章节预览（防 OOM，不读取正文仅统计字数）与新老索引对比
  - 单本应用（支持前端微调章节 new_index / new_volume 后保存）
  - 未处理小说队列 + 分步自动修复（书粒度无状态循环，替代旧有状态批量扫描）
  - 一键重建章节索引端点（import/fix 双 nonce 白名单，供两个页签复用）
- **Novel Manager 单篇触发统计**：小说编辑页发布框新增「Recalculate Stats」入口 ——
  - 打开一本从未统计过的小说会自动算一次（`data-needs-sync` 标记），字段为空时不再需要跑去设置页
  - 按钮走 `recount_novel()`：先补这本书自己的章节字数缓存，再聚合回写，避免使用陈旧缓存算出偏小的字数
  - 小说保存（`save_post_novel`，优先级 99）亦会汇总一次，且排在 ACF 表单写库之后，防止表单里的空值把统计结果覆盖掉
  - 单本 AJAX 权限由「仅 manage_options」放宽到「能编辑这本书即可」
  - 结果由 JS 直接写回 ACF 的 count-chapters / count-words 输入框，无需刷新页面

### 变更 (Changed)

- **Novel Manager Fixer 重构为「聚合根 + 权威计算」**：全面复用 W2P_Novel_Helper 权威算法（含「卷/部/回」「卷部集册」新规则），删除旧 scan_batch/execute_batch 有状态上下文循环；JS 统一扩至约 1300 行并移除旧扫描控件 ID。
- **chapter 字数批量写库改为拼批 SQL**：原来逐条 `update_post_meta` 实测 6.59 ms/条（30 万章 ≈ 34 分钟），改为「已存在行按 meta_id 批量 UPDATE + 不存在行批量 INSERT」后降到 0.077 ms/条（约 86 倍）。全站回填 Phase 1 估算从约 25 分钟降到约 3 分钟。
- **书籍聚合查询重新定型**：`meta_value` 是 LONGTEXT，原先按整型比较导致索引失效；改为字符串比较并 `STRAIGHT_JOIN` 从 postmeta 驱动。单本聚合 1.93 s → 0.18 ms。
- **架构图重绘**：docs/wpgenius-architecture.html 节点 15→16，Novel Manager 独立上主图并标注 ACF / novel+chapter CPT 依赖；Archify showcase 9/9 校验 + 四视口视觉检查通过（5 处源码证据均核实行号）。

### 修复 (Fixed)

- **图片瀑布流顺序错乱（前端增强 · Lightbox · Enable Masonry Layout）**：原实现把连续图片包进 `columns: N` 的 CSS 多列容器，而多列布局是**列优先**填充 —— 6 张图会被均衡切成「列1=1,2｜列2=3,4｜列3=5,6」，视觉顺序变成 1,3,5 / 2,4,6，而不是期望的 1,2,3 / 4,5,6。填充方向是多列布局的固有语义，改列数、改间距都修正不了，因此换为实现「最短列优先」：
  - 新增 `assets/js/masonry.js`（约 40 行，无依赖）：按 DOM 顺序把每张图放进当前最矮的列，顶行即 1,2,3，且横竖图混排不留行内空白
  - `class-masonry-handler.php` 改为输出 `--w2p-cols` 内联 CSS 变量（不再写死 `columns`）；`masonry.css` 在 JS 接管前保留多列作为无 JS 降级，JS 生效后（`data-masonry="on"`）切换为绝对定位
  - 窄屏自动减列：可用宽度不足 180px/列时自动减少列数（容器 500px → 2 列，350px → 1 列）
  - 图片异步加载完成后自动重排一次，窗口缩放 150ms 防抖后重排
  - 原生 CSS `grid-template-rows: masonry` 实测在 Chrome 154 全部返回 `false`（`lanes` / `grid-lanes` / `item-pack` 亦然），caniuse 显示仅 Safari 26.4+ 稳定支持，故不能作为方案
  - 新增 `docs/masonry-layout-check.html`：内置 10 项断言的自检页，双击即可运行，布局回归时直接变红

- **统计概览慢查询泄漏到全后台**：`W2P_Novel_Stats::get_overview()`（30 万章聚合）原本随 `options.php` 在每个后台请求（含 Dashboard）被 include 时即时执行，Query Monitor 报 4 条慢查询。改为三层收敛：① `tab-stats.php` 增加页面门禁，非 `wp-genius-settings` 页一律输出空字符串、零 SQL；② 设置页上概览改为占位骨架（`—`），不再随页面渲染实时计算；③ 新增 `w2p_novel_stats_overview` AJAX 端点 + JS 懒加载，统计 Tab 第一次真正可见时才拉取一次，回填按钮开跑前先确保 totals 就绪。

- **postmeta 上的 `meta_value = %d` 索引失效**：`get_novel_chapter_ids()` / `count_novel_chapters()`（级联删除与删除弹窗计数依赖）改用字符串比较，实测 1.6 s → 1.6 ms。
- **数据完整性**（Ponytail 强制审核闭环产出）：
  - save_novel_chapters_custom 空 new_index 不再覆盖已有章节索引（与 new_volume 守卫对称）
  - fix_single_novel 无章节时不再误标「已完成」，避免数据缺失的小说被永久跳过
  - 自定义章节写入前校验 post_type === 'chapter'，补回旧 execute_batch 的归属检查
- 级联删除弹窗样式随 enqueue 覆盖 edit-novel 屏一并修正（上一版本遗留的裸奔问题）。

### 工程 (Engineering)

- PHP lint / phpcs（WPCS）全部通过；JS node --check 通过；phpcbf 自动修复对齐。
- masonry 布局改动经 headless Chrome 实测：600px 容器顶行 3 张、500px 2 张、350px 1 张，坐标与手算逐像素吻合；无 JS 时确认降级为多列（容器高 408px，而非竖排堆叠的 1092px）。
- **项目规则强化：四步强制交付流程**（`.agent/rules/wpgenius-rules.md`）—— 把原有"前置审查 + 后置评估"骨架补成可执行闭环：`① Ponytail 前置审查（审查子代理 Reviewer） → ② 执行改动 → ③ QA 子代理校验（编码 + 执行结果双维度） → ④ 交付`。明确子代理启动方式（`Agent` + `general-purpose`，只读为提示词级约束）、QA 输入三要素（文件路径 + 校验命令 + 需求原文）、PASS/FAIL 产出格式与 FAIL 回环重跑；补齐 Ponytail 输出格式与"不许偷懒"清单；新增纯问答 / 单行微改等豁免条款。规则收敛为**单一权威源**，`AGENTS.md` / `CLAUDE.md` 降为指针，避免两套规则互相矛盾。

## [2.0.20260903] - 2026-09-03

### 新增 (Added)

- **Novel Manager（小说管理）整体重写**：新增 `W2P_Novel_Importer`（DOCX/TXT 批量导入、两阶段预览微调、断点续传）、`W2P_Novel_Fixer`（章节索引重构、分卷自动识别）、`W2P_Novel_Helper`（中文数字/分卷/章节号解析工具），统一前端脚本 `novel-manager.js`。
- **Smart AUI**：新增文章外链媒体筛选与批量移动；扫描日志集成进度模态框；线程图片预览（no-referrer）；编辑器/批量编辑进度弹窗恢复。
- **Actor Scanner（演员扫描）**：新增完整模块 —— gfriends 索引同步、演员匹配（`W2P_Actor_Matcher`）、批量检测与同步（`W2P_Actor_Sync`）、CLI 工具（`W2P_Actor_Scanner_CLI`），及针对 ACF 与 `humans` 分类法的防御性依赖检查。
- **Media Engine**：内容 URL 与格式修复器（MinIO WebP 探测）、批量工作流整合（共享控制台/日志/前置安全检查）、残留媒体审计。

### 变更 (Changed)

- **Novel Manager 精简重构**：移除旧版 `class-fix-chapter-index.php` / `class-word-to-posts.php` / `class-docx-importer.php` / `class-upload-handler.php` 与两套旧 JS（净删约 2100 行），收敛为 Importer / Fixer / Helper 三件套；TXT 与 DOCX 解析共享章节切分状态机；级联删除弹窗从 PHP 内联迁移至统一 JS 并按需注入。
- **Smart AUI**：设置页合并为 2 个标签页；根目录 PHP/视图文件移入 `includes/` 与 `views/`；ImageProcessor 直接继承扩展（消除 ReflectionClass）；CSS 复用 `core.css` 组件变量（-148 行）。
- **模块瘦身（God class 拆分）**：accelerate 989→247、smart-aui 1312→416、ai-engine 770→400、auto-publish 600→242、word-to-post 727→486 行；frontend-enhancement 资源入队方法 152→23 行。
- **System Health**：自绘 tab 迁移至 CSF tabbed 原生选项卡。
- **CMS Migrator**：移除已弃用模块及关联代码。

### 性能 (Performance)

- Smart AUI 外链筛选：全文扫描改为两阶段覆盖索引查询 + transient 缓存；批处理改为单批插入 + 内存缓存 + 降低批上限。
- Media Engine：批次容量失控修复（20→42/80 膨胀）。

### 修复 (Fixed)

- 安全：移除全局 `sslverify` 绕过并封装 ImageDownloader 请求参数；AJAX 处理器强制对象级权限校验；统一 i18n textdomain 至 `wp-genius`。
- 稳定性：Media Engine admin-ajax 502 根治（QM header 输出禁用 + 前端批次重试容错）、`clean_post_cache` 改 `wp_cache_delete` 精准失效、原子锁 + Throwable + 幂等语义、失败附件防死循环；移除未定义的 `ajax_scanner` 死代码方法。
- 其他：小说列表页级联删除弹窗样式丢失修复（enqueue 覆盖 edit-novel 屏）；移除已删除 `display_admin_notices()` 后遗留的僵尸 transient 写入（`w2p_admin_settings_errors`）。

### 工程 (Engineering)

- GitHub Actions CI 质量门禁；目录 `index.php` 哨兵 + ABSPATH 守卫补齐；`W2P_VERSION` 常量统一版本号；激活/停用生命周期（G2/G3）+ 自定义表 schema 版本控制（`w2p_db_version`）；WP.org 兼容 `readme.txt`。
- 新增项目级 `ponytail` 技能与强制审核员工作流（`.agent/rules/wpgenius-rules.md`）。
- 新增 `docs/wpgenius-architecture.html` 高层架构图（Archify showcase，9/9 视觉校验通过）。

## [1.2.0] - 2025-12

安全加固与架构重构（详见 readme.txt / 上一版本记录）。

[2.0.20260903]: https://github.com/sotarylen/wpgenius/compare/bdea482...a6c4011

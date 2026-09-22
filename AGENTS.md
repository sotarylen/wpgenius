# AGENTS.md

> 本仓库的**项目规则唯一权威源**是 `.agent/rules/wpgenius-rules.md`（`trigger: always_on`）。
>
> **刻意不在本文件重复规则内容。** 两份规则各写各的，最后必然互相矛盾——要改规则，改权威源那一个文件。

## 权威源涵盖

- **全局硬约束**：语言（简体中文）、安全（sanitize / `$wpdb->prepare()` / Nonce / 禁硬编码密钥）、**SubAgent 审查与校验闭环**、Ponytail 工程准则
- **项目特定规则**：i18n（textdomain `wp-genius`、源码原始字符串必须英文）、目录分层、命名前缀（`w2p_` / `wpg_`）、测试与部署环境
- **负向约束**：禁止事项清单
- **范例**：Bad / Good 代码对照

## 核心流程速览（仅供定位，细节一律以权威源为准）

```
① Ponytail 前置审查（审查子代理 Reviewer） → ② 执行改动 → ③ QA 子代理校验（编码 + 执行结果） → ④ 交付
```

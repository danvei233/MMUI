
# 轻舟云主机系统MMUI模板 MMUI Template for Qzsystem - LightningBoatX
[![GPLv3 License](https://img.shields.io/badge/License-GPL%20v3-yellow.svg)](https://opensource.org/licenses/) 
[![GitHub Release](https://img.shields.io/github/release/danvei233/mmui.svg?style=flat)]()  
面向轻舟云主机系统的现代化控制台主题。 

欢迎使用轻舟云主机系统MMUI模板！

本模板是专为轻舟云系统用户设计的前台界面模板，提供两种不同的主题样式供用户选择：正常模式和黑暗模式。 

快捷下载地址（https://wwbfg.lanzouu.com/iATHS4081pnc ）

MMUI V2X 美化了轻舟 ECS 控制台和机器登录页，让云主机管理更清晰、更顺手，更适合日常高频操作。

<img width="2247" height="1219" alt="image" src="https://github.com/user-attachments/assets/39c637f2-ff54-46a1-8daf-23073a04e6ab" />

<img width="2247" height="1219" alt="image" src="https://github.com/user-attachments/assets/0fb5817c-e178-4715-a345-3593b9189ed5" />


## 新版亮点

- 全新的 ECS 控制台：首页、监控、系统、VNC、网络、快照、备份、端口映射、安全策略、挂机宝建站。
- 更顺手的远程登录：Windows、Linux、桌面端、移动端按场景处理。
- 轻舟后台可切换 MMUI / 原版界面，出问题可以快速回退。
- 支持崭新机器登录页，可在后台单独开启或关闭。
- 支持自定义网站标题、控制台标题、Logo、登录页 Logo。
- 支持debug本地开发模式，方便前端开发时直接加载本地 Vite 服务便捷开发。
- 最小破坏安装，支持自动修复。
- 支持部分字段自定义。

## 安装方法

1. 下载 Release 中的 `mmui-v2x-qz-override.zip`。
2. 备份你的轻舟站点目录。
3. 将压缩包解压到轻舟 `qzsystem` 根目录。
4. 将轻舟目录下的所有目录和文件权限设置为 `775`，并确认文件所有者与 PHP-FPM 运行用户一致。Linux 可在轻舟根目录执行 `chmod -R 775 .`，宝塔可在文件管理中勾选“应用到子目录和文件”。
5. 登录轻舟后台并访问 `/admin/mmui/index`。
6. 首次打开会进入安装向导，确认检测结果后点击“一键安装”。
7. 安装完成会自动进入 MMUI 配置设置，开启控制台或机器登录页并保存。

覆盖包不会直接替换轻舟的核心控制器、Service 或虚拟化驱动。后台安装向导目前验证官方版本列表中的全部 79 个版本（`2021110101` 至 `2026070901`），会将原文件备份到 `runtime/mmui-backup` 后执行幂等补丁。范围外版本会显示兼容提示，但仍会根据实际源码锚点尽最大努力完成安装；只有关键文件缺失、锚点无法识别或写入失败才会阻断。轻舟更新后应重新覆盖 MMUI 包并在设置页执行兼容修复。

全版本矩阵按轻舟实际的增量升级包测试：以完整程序为基线，逐个覆盖每个版本中涉及 MMUI Hook 的官方文件，再验证补丁执行、二次执行幂等性、PHP 语法和 28 个 ECS 控制器接口。可运行 `powershell -ExecutionPolicy Bypass -File tools/Test-QzVersionCompatibility.ps1` 重新生成 JSON、CSV 和 Markdown 报告。

# 注意事项

1.请确保在替换文件前备份原有文件，以防万一需要恢复。
2.我的QQ号484883303，有问题可以联系我，如果您信任的话我可以免费指导您安装，我们的用户交流QQ群526385986。

## 步入开发

ECS 控制台：

```bash
cd MMUI-V2X
npm install
npm run dev
```

机器登录页：

```bash
cd LoginUI
npm install
npm run dev
```

构建覆盖包：

```bash
go run ./scripts/package.go -name mmui-v2x-qz-override.zip
```

输出文件在：

```text
release/mmui-v2x-qz-override.zip
```

## 项目结构

```text
MMUI-V2X/      ECS 控制台 Vue 源码
LoginUI/       机器登录页 Vue 源码
qz-override/   轻舟覆盖层
scripts/       打包脚本
tools/         调试工具，不进入正式覆盖包
```

## 关于入门教程

入门教程组件已经保留，但当前版本默认不自动弹出。需要调试时可以在浏览器控制台执行：

```js
mmuiStartTutorial()
```

## 统计说明

ECS 页面统计默认开启，可在 MMUI 设置里关闭。统计脚本使用 Matomo，禁用 Cookie，只用于了解 MMUI ECS 页面使用情况。

不会收集系统密码、面板密码、用户名、实例名称、具体 IP 原文或端口映射内容。

## 作者

丁薇  
GitHub: [@danvei233](https://github.com/danvei233)  
QQ: 484883303  
中国石油大学（北京）

## 许可证

本项目使用 GPLv3 许可证。  
请不要倒卖本项目，也不要删除项目署名信息。

# 贡献
如果您有任何建议或改进，请随时提交Pull Request或开Issue讨论。

感谢您选择MMUI Template for Qzsystem，希望它能为您的轻舟云系统带来更好的用户体验！

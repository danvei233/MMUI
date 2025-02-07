(function() {
    // 检查并加载外部CSS
    function loadCSS(url) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`link[href="${url}"]`)) {
                resolve();
                return;
            }
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            link.onload = () => resolve();
            link.onerror = () => reject(new Error(`无法加载CSS: ${url}`));
            document.head.appendChild(link);
        });
    }

    // 检查并加载外部JS
    function loadJS(url) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${url}"]`)) {
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.src = url;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`无法加载JS: ${url}`));
            document.body.appendChild(script);
        });
    }

    // 注入CSS样式
    function injectStyles(styles) {
        const styleTag = document.createElement('style');
        styleTag.textContent = styles;
        document.head.appendChild(styleTag);
    }

    // 创建聊天组件的HTML结构
    function createChatHTML() {
        const container = document.createElement('div');
        container.id = 'embedded-chat-widget';
        container.innerHTML = `
            <div class="mdui-drawer" id="drawer">
                <div class="chat-list">
                    <div class="chat-list-header">
                        <span>聊天会话</span>
                        <button class="mdui-btn mdui-btn-icon" id="addChatBtn"><i
                                class="mdui-icon material-icons">add</i></button>
                    </div>
                    <div id="chatList">
                        <!-- 聊天列表将显示在这里 -->
                    </div>
                </div>
            </div>
            <div class="chat-container ">
                <!-- 主内容区域 -->
                <div class="chat-main">
                    <div class="chat-messages" id="chatMessages">
                        <!-- 聊天消息将显示在这里 -->
                    </div>
                    <!-- 返回底部按钮 -->
                    <button class="mdui-fab mdui-fab-mini mdui-color-theme-accent" id="scrollToBottomBtn">
                        <i class="mdui-icon material-icons ">arrow_downward</i>
                    </button>
                    <!-- 函数调用提示框 -->
                    <div id="functionCallTip"></div>
                    <div class="chat-input">
                        <textarea id="userInput" class="mdui-textfield-input" rows="1" placeholder="输入消息"
                            style="flex: 1; resize: none;"></textarea>
                        <button class="mdui-btn mdui-btn-icon" id="sendBtn"><i
                                class="mdui-icon material-icons">send</i></button>
                        <button class="mdui-btn mdui-btn-icon" id="voiceBtn"><i
                                class="mdui-icon material-icons">mic</i></button>
                        <button class="mdui-btn mdui-btn-icon" id="fileUploadBtn"><i
                                class="mdui-icon material-icons">attach_file</i></button>
                        <input type="file" id="fileInput" style="display: none;" multiple />
                    </div>
                </div>
            </div>
            <!-- 配置对话框 -->
            <div class="mdui-dialog" id="configDialog">
                <div class="mdui-dialog-title">配置</div>
                <div class="mdui-dialog-content">
                    <div class="mdui-textfield mdui-textfield-floating-label">
                        <label class="mdui-textfield-label">API Base URL</label>
                        <input class="mdui-textfield-input" type="text" id="apiBaseUrl"
                            value="https://181db8c2fb36d34200f8ce83762f64a2.api-forwards.com/v1/chat/completions" />
                    </div>
                    <div class="mdui-textfield mdui-textfield-floating-label">
                        <label class="mdui-textfield-label">API Key</label>
                        <input class="mdui-textfield-input" type="password" id="apiKey" />
                    </div>
                </div>
                <div class="mdui-dialog-actions">
                    <button class="mdui-btn mdui-btn-icon" id="exportBtn"><i
                        class="mdui-icon material-icons">file_download</i></button>
                    <button class="mdui-btn mdui-btn-icon" id="importBtn"><i
                        class="mdui-icon material-icons">file_upload</i></button>
                    <button class="mdui-btn mdui-ripple" mdui-dialog-close>取消</button>
                    <button class="mdui-btn mdui-ripple" id="saveConfig">保存</button>
                </div>
            </div>
            <!-- 导入文件输入 -->
            <input type="file" id="importFile" accept=".json" style="display: none;" />
            <!-- 右键菜单 -->
            <div id="contextMenu" class="context-menu">
                <div class="context-menu-item" id="copyMessage">复制消息</div>
            </div>
        `;
        return container;
    }

    // 注入聊天组件到指定容器
    function injectChat(containerId) {
        const hostContainer = document.getElementById(containerId);
        if (!hostContainer) {
            console.error(`容器元素 #${containerId} 不存在。`);
            return;
        }
        const chatHTML = createChatHTML();
        hostContainer.appendChild(chatHTML);
    }

    // 加载所有外部依赖并初始化聊天组件
    async function initializeChatWidget(containerId) {
        try {
            // 加载CSS依赖
            await loadCSS('https://cdn.jsdelivr.net/npm/mdui@1.0.2/dist/css/mdui.min.css');
            await loadCSS('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/default.min.css');

            // 注入自定义CSS
            injectStyles(`
               .embedded-chat-widget html, 
.embedded-chat-widget body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden; /* 限制溢出 */
    font-family: 'Helvetica Neue', Arial, 'San Francisco', sans-serif; /* 主字体 */
    line-height: 1.6; /* 提升可读性 */
    color: #333; /* 深灰色文字 */
    background-color: #f4f5f7; /* 柔和的浅灰背景 */
    transition: background-color 0.3s, color 0.3s; /* 平滑过渡 */
}

/* 响应式布局 */
.embedded-chat-widget .chat-container {
    display: flex;
    height: calc(100% - 64px); /* 减去工具栏高度 */
}

@media (max-width: 768px) {
    .embedded-chat-widget .chat-container {
        padding-top: 0; /* 移动端移除顶部内边距 */
        height: calc(100% - var(--toolbar-height)); /* 使用动态变量 */
    }
}

/* 侧边栏样式 */
.embedded-chat-widget .chat-sidebar {
    width: 240px;
    border-right: 1px solid #ddd;
    overflow-y: auto;
    background-color: #ffffff;
    transition: background-color 0.3s, color 0.3s;
}

/* 主聊天区样式 */
.embedded-chat-widget .chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    position: relative;
}

/* 聊天消息区域 */
.embedded-chat-widget .chat-messages {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background-color: #f9f9f9; /* 消息区域背景色 */
    display: flex;
    flex-direction: column;
    transition: background-color 0.3s, color 0.3s;
}

/* 输入框区域 */
.embedded-chat-widget .chat-input {
    padding: 16px;
    border-top: 1px solid #dcdcdc; /* 分隔线 */
    display: flex;
    align-items: center;
    gap: 8px;
    background-color: #ffffff; /* 白色背景 */
    transition: background-color 0.3s, border-color 0.3s;
}

.embedded-chat-widget .chat-input textarea {
    flex: 1;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    resize: none;
    transition: border-color 0.3s, background-color 0.3s, color 0.3s;
}

.embedded-chat-widget .chat-input textarea:focus {
    border-color: #4dabf7; /* 聚焦时的边框颜色 */
    outline: none;
    background-color: #f0f8ff; /* 聚焦时背景颜色 */
}

/* 消息样式 */
.embedded-chat-widget .message {
    margin-bottom: 12px;
    position: relative;
    display: flex;
    align-items: flex-start;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeInUp 0.3s forwards; /* 消息淡入动画 */
}

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* 用户消息对齐 */
.embedded-chat-widget .message.user {
    flex-direction: row-reverse;
}

/* 助手消息对齐 */
.embedded-chat-widget .message.assistant,
.embedded-chat-widget .message.function {
    flex-direction: row;
}

/* 用户消息背景和文字颜色 */
.embedded-chat-widget .message.user .content {
    background-color: #4dabf7; /* 主题蓝色背景 */
    color: #ffffff; /* 白色文字 */
    align-self: flex-end;
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
    border-bottom-left-radius: 15px;
    transition: background-color 0.3s, color 0.3s;
}

/* 助手消息背景和文字颜色 */
.embedded-chat-widget .message.assistant .content {
    background-color: #e3f2fd; /* 浅蓝色背景 */
    color: #1a1a1a; /* 深灰色文字 */
    align-self: flex-start;
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
    border-bottom-right-radius: 15px;
    transition: background-color 0.3s, color 0.3s;
}

/* 函数消息背景和文字颜色 */
.embedded-chat-widget .message.function .content {
    background-color: #c3eafe; /* 更浅的蓝色背景 */
    color: #1a1a1a; /* 深灰色文字 */
    align-self: flex-start;
    border-radius: 15px;
    transition: background-color 0.3s, color 0.3s;
}

/* 头像样式 */
.embedded-chat-widget .avatar {
    width: 40px;
    aspect-ratio: 1;
    object-fit: cover;
    height: 40px;
    border-radius: 50%;
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 10px;
    background-color: #e3f2fd; /* 与助手背景一致 */
    color: #1a1a1a;
    transition: background-color 0.3s, color 0.3s;
}

/* 消息内容样式 */
.embedded-chat-widget .content {
    position: relative;
    padding: 12px 16px;
    border-radius: 10px;
    max-width: 70%;
    word-wrap: break-word;
    transition: background-color 0.3s, color 0.3s;
    font-size: 15px;
    font-family: 'Helvetica Neue', Arial, 'San Francisco', sans-serif;
    color: #333;
    letter-spacing: 0.2px;
}

.embedded-chat-widget .message:hover .content {
    background-color: #d0e7ff; /* 淡蓝色 */
}

/* 代码块样式 */
.embedded-chat-widget .code-block {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 8px;
    font-family: Consolas, monospace;
    font-size: 14px;
    position: relative;
    overflow-x: auto;
    margin: 20px 0;
    transition: background-color 0.3s, border-color 0.3s;
}

.embedded-chat-widget .code-block pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.embedded-chat-widget .code-block .language-label {
    top: -10px;
    right: 15px;
    background-color: #4dabf7; /* 主题蓝色 */
    color: white;
    padding: 2px 8px;
    font-size: 12px;
    font-family: Arial, sans-serif;
    border-radius: 3px;
    transition: background-color 0.3s;
}

/* 复制按钮样式 */
.embedded-chat-widget .copy-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    background-color: #4dabf7; /* 主题蓝色 */
    color: #ffffff;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    border-radius: 3px;
    font-size: 12px;
    transition: background-color 0.3s;
}

.embedded-chat-widget .copy-btn:hover {
    background-color: #357ab8; /* 深蓝色 */
}

/* 动画效果 */
@keyframes scroll-light {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

/* "正在思考..." 文本的样式 */
.embedded-chat-widget .loading-message .content {
    background-color: #e3f2fd; /* 浅蓝色背景 */
    color: #6c757d; /* 灰色文字 */
    font-style: italic;
    position: relative;
    overflow: hidden;
    transition: background-color 0.3s, color 0.3s;
}

.embedded-chat-widget .loading-message .content::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.4) 50%, rgba(255, 255, 255, 0) 100%);
    animation: scroll-light 1.5s infinite;
}

/* 闪烁光标样式 */
.embedded-chat-widget .blinking-cursor {
    display: inline;
    color: #6c757d;
    font-weight: bold;
    animation: blink 1s step-start infinite;
}

@keyframes blink {
    50% {
        opacity: 0;
    }
}

/* 按钮样式 */
.embedded-chat-widget .message-buttons {
    margin-top: 5px;
    display: flex;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.3s;
    height: 24px;
}

.embedded-chat-widget .message:hover .message-buttons {
    opacity: 1;
}

.embedded-chat-widget .delete-btn,
.embedded-chat-widget .edit-btn,
.embedded-chat-widget .copy-btn,
.embedded-chat-widget .regenerate-btn {
    background: none;
    border: none;
    color: #4dabf7; /* 主题蓝色 */
    cursor: pointer;
    font-size: 18px;
    transition: color 0.3s;
}

.embedded-chat-widget .delete-btn:hover,
.embedded-chat-widget .edit-btn:hover,
.embedded-chat-widget .copy-btn:hover,
.embedded-chat-widget .regenerate-btn:hover {
    color: #357ab8; /* 深蓝色 */
}

/* Markdown 简单样式 */
.embedded-chat-widget .content h1 {
    font-size: 1.5em;
    color: #4dabf7;
}

.embedded-chat-widget .content h2 {
    font-size: 1.3em;
    color: #4dabf7;
}

.embedded-chat-widget .content h3 {
    font-size: 1.1em;
    color: #4dabf7;
}

.embedded-chat-widget .content strong {
    font-weight: bold;
}

.embedded-chat-widget .content em {
    font-style: italic;
}

.embedded-chat-widget .content code {
    background-color: #eee;
    padding: 2px 4px;
    border-radius: 4px;
}

.embedded-chat-widget .content pre {
    border-radius: 4px;
    overflow-x: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
    position: relative;
}

.embedded-chat-widget pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* 聊天列表样式 */
.embedded-chat-widget .chat-list-header {
    padding: 16px;
    border-bottom: 1px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #fff;
    transition: background-color 0.3s, color 0.3s;
}

.embedded-chat-widget .chat-item {
    padding: 12px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background-color 0.3s, color 0.3s;
}

.embedded-chat-widget .chat-item.active {
    background-color: #c3eafe; /* 淡蓝色 */
}

.embedded-chat-widget .chat-item:hover {
    background-color: #e0f7fa; /* 更浅的蓝色 */
}

.embedded-chat-widget .chat-item .chat-title {
    flex: 1;
}

.embedded-chat-widget .chat-item .delete-chat-btn {
    background: none;
    border: none;
    color: #4dabf7; /* 主题蓝色 */
    cursor: pointer;
    margin-left: 8px;
    transition: color 0.3s;
}

.embedded-chat-widget .chat-item .delete-chat-btn:hover {
    color: #357ab8; /* 深蓝色 */
}

/* 代码块复制按钮样式 */
.embedded-chat-widget .code-copy-btn,
.embedded-chat-widget .code-preview-btn {
    display: none;
}

.embedded-chat-widget pre:hover .code-copy-btn,
.embedded-chat-widget pre:hover .code-preview-btn {
    display: block;
}

/* 右键菜单样式 */
.embedded-chat-widget .context-menu {
    position: absolute;
    z-index: 1000;
    background-color: #ffffff;
    border: 1px solid #ddd;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    display: none;
    min-width: 120px;
    transition: background-color 0.3s, color 0.3s;
}

.embedded-chat-widget .context-menu-item {
    padding: 8px 12px;
    cursor: pointer;
    white-space: nowrap;
    transition: background-color 0.3s, color 0.3s;
}

.embedded-chat-widget .context-menu-item:hover {
    background-color: #e0f7fa; /* 更浅的蓝色 */
}

/* 返回底部按钮样式 */
.embedded-chat-widget #scrollToBottomBtn {
    position: fixed;
    bottom: 80px;
    right: 30px;
    display: none;
    z-index: 999;
    background-color: #4dabf7; /* 主题蓝色 */
    color: #ffffff;
    border: none;
    padding: 10px 15px;
    border-radius: 50px;
    cursor: pointer;
    transition: background-color 0.3s, transform 0.3s;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.embedded-chat-widget #scrollToBottomBtn:hover {
    background-color: #357ab8; /* 深蓝色 */
    transform: translateY(-2px);
}

/* 模型选择器样式 */
.embedded-chat-widget #modelSelector {
    width: 150px;
    padding: 5px;
    border-radius: 5px;
    border: 1px solid #4dabf7;
    background-color: #ffffff;
    color: #333;
    transition: border-color 0.3s;
}

.embedded-chat-widget #modelSelector:hover,
.embedded-chat-widget #modelSelector:focus {
    border-color: #357ab8;
    outline: none;
}

/* 函数调用提示框样式 */
.embedded-chat-widget #functionCallTip {
    position: fixed;
    top: 80px;
    right: 30px;
    background-color: #ffffff;
    border: 1px solid #4dabf7;
    padding: 10px 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    display: none;
    z-index: 1000;
    border-radius: 5px;
    transition: background-color 0.3s, border-color 0.3s, color 0.3s;
}

/* 动画和过渡优化 */
.embedded-chat-widget .gridA {
    display: flex;
    align-content: space-around;
    flex-direction: column;
    justify-content: center;
    align-items: flex-end;
    transition: all 0.3s ease-in-out;
}

/* 跑马灯动画条 */
.embedded-chat-widget .loading-message .content::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.4) 50%, rgba(255, 255, 255, 0) 100%);
    animation: scroll-light 1.5s infinite;
}

/* 闪烁动画 */
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}

/* 返回底部按钮的显示逻辑 */
.embedded-chat-widget #scrollToBottomBtn.show {
    display: block;
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* 主题切换（深色模式） */
@media (prefers-color-scheme: dark) {
    .embedded-chat-widget body {
        color: #eaeaea; /* 浅灰色文字 */
        background-color: #121212; /* 深色背景 */
    }

    .embedded-chat-widget .chat-sidebar,
    .embedded-chat-widget .chat-main,
    .embedded-chat-widget .chat-messages,
    .embedded-chat-widget .chat-input,
    .embedded-chat-widget .chat-list-header,
    .embedded-chat-widget .chat-item,
    .embedded-chat-widget .context-menu,
    .embedded-chat-widget #functionCallTip,
    .embedded-chat-widget .code-block,
    .embedded-chat-widget .copy-btn,
    .embedded-chat-widget #scrollToBottomBtn,
    .embedded-chat-widget #modelSelector {
        transition: background-color 0.3s, color 0.3s, border-color 0.3s;
    }

    .embedded-chat-widget #functionCallTip {
        color: #eaeaea;
        background-color: #1e1e1e;
        border: 1px solid #4dabf7;
        box-shadow: 0 2px 5px rgba(32, 34, 61, 0.349);
        display: none;
        z-index: 1000;
    }

    /* 工具栏 */
    .embedded-chat-widget .mdui-appbar {
        background-color: #1e1e1e !important; /* 深色工具栏背景 */
        color: #eaeaea;
        transition: background-color 0.3s, color 0.3s;
    }

    /* 聊天消息背景 */
    .embedded-chat-widget .chat-messages {
        background-color: #1e1e1e; /* 深灰背景 */
        color: #eaeaea; /* 浅灰色文字 */
    }

    /* 输入框 */
    .embedded-chat-widget .chat-input {
        background-color: #1e1e1e; /* 深灰背景 */
        border-top: 1px solid #333;
        color: #eaeaea; /* 浅灰色文字 */
    }

    .embedded-chat-widget .chat-input textarea {
        background-color: #2a2a2a; /* 更深的输入框背景 */
        color: #eaeaea;
    }

    .embedded-chat-widget .chat-input textarea:focus {
        border-color: #4dabf7; /* 聚焦时的边框颜色 */
    }

    /* 用户消息 */
    .embedded-chat-widget .message.user .content {
        background-color: #4dabf7; /* 主题蓝色背景 */
        color: #ffffff; /* 白色文字 */
    }

    /* 助手消息 */
    .embedded-chat-widget .message.assistant .content {
        background-color: #3d3d3d; /* 深灰背景 */
        color: #eaeaea; /* 浅灰文字 */
    }

    /* 函数消息 */
    .embedded-chat-widget .message.function .content {
        background-color: #4b3f27; /* 棕色背景 */
        color: #ffffff; /* 白色文字 */
    }

    /* 按钮样式 */
    .embedded-chat-widget .mdui-btn {
        background-color: #1e1e1e; /* 深灰背景 */
        color: #eaeaea;
        transition: background-color 0.3s, color 0.3s;
    }

    .embedded-chat-widget .mdui-btn:hover {
        background-color: #3d3d3d;
    }

    /* 聊天列表 */
    .embedded-chat-widget .chat-list-header {
        background-color: #1e1e1e;
        color: #eaeaea;
    }

    .embedded-chat-widget .chat-item {
        background-color: #1e1e1e;
        color: #eaeaea;
    }

    .embedded-chat-widget .chat-item:hover {
        background-color: #333;
    }

    .embedded-chat-widget .chat-item.active {
        background-color: #4dabf7; /* 主题蓝色 */
        color: #fff;
    }

    /* 右键菜单 */
    .embedded-chat-widget .context-menu {
        background-color: #1e1e1e;
        color: #eaeaea;
        border: 1px solid #333;
    }

    .embedded-chat-widget .context-menu-item:hover {
        background-color: #333;
    }

    /* 代码块 */
    .embedded-chat-widget pre code {
        background-color: #2a2a2a;
        color: #eaeaea;
    }

    .embedded-chat-widget .code-block .language-label {
        background-color: #4dabf7;
    }

    .embedded-chat-widget .copy-btn {
        background-color: #2a2a2a;
        color: #eaeaea;
    }

    .embedded-chat-widget .copy-btn:hover {
        background-color: #3d3d3d;
    }

    /* 返回底部按钮 */
    .embedded-chat-widget #scrollToBottomBtn {
        background-color: #4dabf7;
        color: #ffffff;
    }

    .embedded-chat-widget #scrollToBottomBtn:hover {
        background-color: #357ab8;
    }
}

/* 现代舒适的动效优化 */
/* 平滑过渡 */
.embedded-chat-widget * {
    transition: all 0.3s ease-in-out;
}

/* 按钮轻微放大效果 */
.embedded-chat-widget button, 
.embedded-chat-widget .copy-btn, 
.embedded-chat-widget .delete-btn, 
.embedded-chat-widget .edit-btn, 
.embedded-chat-widget .regenerate-btn {
    transition: transform 0.2s ease-in-out, background-color 0.3s;
}

.embedded-chat-widget button:hover, 
.embedded-chat-widget .copy-btn:hover, 
.embedded-chat-widget .delete-btn:hover, 
.embedded-chat-widget .edit-btn:hover, 
.embedded-chat-widget .regenerate-btn:hover {
    transform: scale(1.05);
}

/* 侧边栏滚动条样式 */
.embedded-chat-widget .chat-sidebar::-webkit-scrollbar {
    width: 8px;
}

.embedded-chat-widget .chat-sidebar::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.embedded-chat-widget .chat-sidebar::-webkit-scrollbar-thumb {
    background: #4dabf7;
    border-radius: 4px;
}

.embedded-chat-widget .chat-sidebar::-webkit-scrollbar-thumb:hover {
    background: #357ab8;
}

/* 聊天消息区滚动条样式 */
.embedded-chat-widget .chat-messages::-webkit-scrollbar {
    width: 8px;
}

.embedded-chat-widget .chat-messages::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.embedded-chat-widget .chat-messages::-webkit-scrollbar-thumb {
    background: #4dabf7;
    border-radius: 4px;
}

.embedded-chat-widget .chat-messages::-webkit-scrollbar-thumb:hover {
    background: #357ab8;
}
            `);

            // 加载JS依赖
            await loadJS('https://unpkg.com/mdui@1.0.2/dist/js/mdui.min.js');
            await loadJS('https://cdn.jsdelivr.net/npm/marked/marked.min.js');
            await loadJS('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js');
            await loadJS('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/languages/javascript.min.js');

            // 注入聊天组件的HTML
            injectChat(containerId);

            // 初始化聊天组件的JavaScript逻辑
            // 为避免全局变量冲突，可以将所有代码封装在一个函数或模块中
            // 这里假设您的聊天逻辑代码已经被调整为一个可调用的函数
            // 例如，您可以将原有的<script>部分封装为initChat()函数
            // 并在此处调用它
            // 由于您的原代码较长，这里仅提供一个示例框架

            // 示例：假设您将原有的聊天逻辑封装在一个名为 initChat 的函数中
            // initChat(containerId);
            // 具体实现请根据您的实际代码进行调整

            // 以下是一个简化的初始化示例
            // 您需要将原有的JavaScript逻辑适配到此结构中
            initChatWidgetLogic();

        } catch (error) {
            console.error('聊天组件初始化失败:', error);
        }
    }

    // 聊天组件的JavaScript逻辑
    function initChatWidgetLogic() {
        // 这里粘贴您原有的<script>中的JavaScript代码
        // 需要对代码进行适当的调整，以确保它在嵌入模式下正常工作
        // 例如，确保选择器指向#embedded-chat-widget内的元素

        // 示例：
        const chatContainer = document.getElementById('embedded-chat-widget');
        if (!chatContainer) return;

        // 以下是对您原有代码的适配示例
        // 您需要将所有document.getElementById等选择器修改为基于chatContainer
        // 以确保只操作嵌入组件内的元素

        const chatMessages = chatContainer.querySelector('#chatMessages');
        const userInput = chatContainer.querySelector('#userInput');
        const sendBtn = chatContainer.querySelector('#sendBtn');
        const voiceBtn = chatContainer.querySelector('#voiceBtn');
        const fileUploadBtn = chatContainer.querySelector('#fileUploadBtn');
        const fileInput = chatContainer.querySelector('#fileInput');
        const exportBtn = chatContainer.querySelector('#exportBtn');
        const importBtn = chatContainer.querySelector('#importBtn');
        const addChatBtn = chatContainer.querySelector('#addChatBtn');
        const importFile = chatContainer.querySelector('#importFile');
        const chatList = chatContainer.querySelector('#chatList');
        const contextMenu = chatContainer.querySelector('#contextMenu');
        const copyMessageItem = chatContainer.querySelector('#copyMessage');
        const scrollToBottomBtn = chatContainer.querySelector('#scrollToBottomBtn');
        const modelSelector = chatContainer.querySelector('#modelSelector');
        const functionCallTip = chatContainer.querySelector('#functionCallTip');

        let clickedMessageContent = ''; // 存储被右键点击的消息内容

        // 配置变量
        let apiBaseUrl = localStorage.getItem('apiBaseUrl') || 'https://api.openai.com/v1/chat/completions';
        let apiKey = localStorage.getItem('apiKey') || '';
        let selectedModel = localStorage.getItem('selectedModel') || 'gpt-3.5-turbo';

        // 聊天会话列表
        let chats = JSON.parse(localStorage.getItem('chats')) || [];
        let currentChatId = localStorage.getItem('currentChatId') || null;

        // 聊天记录管理
        function getChatById(id) {
            return chats.find(chat => chat.id === id);
        }

        function saveChats() {
            localStorage.setItem('chats', JSON.stringify(chats));
            localStorage.setItem('currentChatId', currentChatId);
        }

        // 渲染聊天列表
        function renderChatList() {
            chatList.innerHTML = '';
            chats.forEach(chat => {
                const chatItem = document.createElement('div');
                chatItem.classList.add('chat-item');
                if (chat.id === currentChatId) {
                    chatItem.classList.add('active');
                }

                const chatTitle = document.createElement('div');
                chatTitle.classList.add('chat-title');
                chatTitle.textContent = chat.name;

                const deleteChatBtn = document.createElement('button');
                deleteChatBtn.classList.add('delete-chat-btn');
                deleteChatBtn.innerHTML = '<i class="mdui-icon material-icons">delete</i>';
                deleteChatBtn.title = '删除聊天';
                deleteChatBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    deleteChat(chat.id);
                });

                chatItem.appendChild(chatTitle);
                chatItem.appendChild(deleteChatBtn);
                chatItem.addEventListener('click', () => {
                    switchChat(chat.id);
                });

                chatList.appendChild(chatItem);
            });
        }

        // 切换聊天会话
        function switchChat(id) {
            currentChatId = id;
            saveChats();
            renderChatList();
            renderChat();
        }

        // 添加新的聊天会话
        function addNewChat(name = '新聊天') {
            const newChat = {
                id: Date.now().toString(),
                name: name,
                messages: []
            };
            chats.push(newChat);
            currentChatId = newChat.id;
            saveChats();
            renderChatList();
            renderChat();
        }

        // 删除聊天会话
        function deleteChat(id) {
            if (!confirm('确定要删除此聊天会话吗？')) return;
            chats = chats.filter(chat => chat.id !== id);
            if (currentChatId === id) {
                currentChatId = chats.length > 0 ? chats[chats.length - 1].id : null;
            }
            saveChats();
            renderChatList();
            renderChat();
        }

        // 渲染当前聊天
        function renderChat() {
            chatMessages.innerHTML = '';
            const currentChat = getChatById(currentChatId);
            if (!currentChat) {
                chatMessages.innerHTML = '<div class="mdui-typo">请选择或创建一个聊天会话</div>';
                return;
            }

            // 确保每条消息都有唯一的 id
            currentChat.messages.forEach(msg => {
                if (!msg.id) {
                    msg.id = Date.now().toString() + Math.random().toString(36).substr(2, 9);
                }
                appendMessage(msg);
            });
        }

        // 添加消息到聊天记录并更新界面
        function addMessage(message) {
            const currentChat = getChatById(currentChatId);
            if (currentChat) {
                message.id = Date.now().toString() + Math.random().toString(36).substr(2, 9);
                currentChat.messages.push(message);
                saveChats();
                appendMessage(message);
                // 自动生成聊天标题
                if (message.role === 'user') {
                    generateChatTitle();
                }
            }
        }

        // 追加单条消息到界面
        function appendMessage(msg) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', msg.role);
            messageDiv.id = `message-${msg.id}`;

            // 添加头像（使用Emoji）
            const avatarSpan = document.createElement('span');
            avatarSpan.classList.add('avatar');
            if (msg.role === 'user') {
                avatarSpan.textContent = '👤'; // 用户头像Emoji
            } else if (msg.role === 'assistant') {
                avatarSpan.textContent = '🤖'; // 助手头像Emoji
            } else if (msg.role === 'function') {
                avatarSpan.textContent = '🔧'; // 函数头像Emoji
            } else {
                avatarSpan.textContent = '❓'; // 默认头像Emoji
            }

            // 创建内容容器
            const contentDiv = document.createElement('div');
            contentDiv.classList.add('content');
            contentDiv.innerHTML = parseMarkdown(msg.content);

            // 如果是加载中的消息，添加特殊的类
            if (msg.loading) {
                messageDiv.classList.add('loading-message');
                contentDiv.innerHTML = '正在思考...';
            }

            // 右键菜单事件
            contentDiv.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                clickedMessageContent = msg.content;
                showContextMenu(e.pageX, e.pageY);
            });

            // 创建按钮容器
            const buttonsDiv = document.createElement('div');
            buttonsDiv.classList.add('message-buttons');

            // 添加删除按钮
            const deleteBtn = document.createElement('button');
            deleteBtn.classList.add('delete-btn');
            deleteBtn.innerHTML = '<i class="mdui-icon material-icons">delete</i>';
            deleteBtn.title = '删除消息';
            deleteBtn.addEventListener('click', () => {
                deleteMessage(msg.id);
            });
            buttonsDiv.appendChild(deleteBtn);

            // 添加编辑按钮
            const editBtn = document.createElement('button');
            editBtn.classList.add('edit-btn');
            editBtn.innerHTML = '<i class="mdui-icon material-icons">edit</i>';
            editBtn.title = '编辑消息';
            editBtn.addEventListener('click', () => {
                editMessage(msg.id);
            });
            buttonsDiv.appendChild(editBtn);

            // 添加重新生成按钮（仅对助手消息）
            if (msg.role === 'assistant' && !msg.loading) {
                const regenerateBtn = document.createElement('button');
                regenerateBtn.classList.add('regenerate-btn');
                regenerateBtn.innerHTML = '<i class="mdui-icon material-icons">autorenew</i>';
                regenerateBtn.title = '重新生成回复';
                regenerateBtn.addEventListener('click', () => {
                    regenerateMessage(msg.id);
                });
                buttonsDiv.appendChild(regenerateBtn);
            }

            // 将按钮容器添加到消息容器
            const messageContentWrapper = document.createElement('div');
            messageContentWrapper.classList.add('gridA');
            messageContentWrapper.appendChild(contentDiv);
            messageContentWrapper.appendChild(buttonsDiv);

            // 将头像和内容添加到消息容器
            if (msg.role === 'user') {
                messageDiv.appendChild(avatarSpan);
                messageDiv.appendChild(messageContentWrapper);
            } else {
                messageDiv.appendChild(avatarSpan);
                messageDiv.appendChild(messageContentWrapper);
            }

            // 对代码块进行语法高亮和添加按钮
            if (!msg.loading) {
                contentDiv.querySelectorAll('pre').forEach((pre) => {
                    const code = pre.querySelector('code');
                    const codeContent = code.innerText;
                    const language = code.className || ''; // 获取语言

                    // 语法高亮
                    hljs.highlightElement(code);

                    // 创建复制按钮
                    const copyButton = document.createElement('button');
                    copyButton.classList.add('code-copy-btn');
                    copyButton.innerHTML = '<i class="mdui-icon material-icons">content_copy</i>';
                    copyButton.title = '复制代码';
                    copyButton.addEventListener('click', () => {
                        navigator.clipboard.writeText(codeContent).then(() => {
                            mdui.snackbar({ message: '代码已复制到剪贴板' });
                        }).catch(err => {
                            mdui.snackbar({ message: '复制失败，请手动复制' });
                        });
                    });
                    copyButton.style.position = 'absolute';
                    copyButton.style.top = '5px';
                    copyButton.style.right = '5px';
                    copyButton.style.background = 'none';
                    copyButton.style.border = 'none';
                    copyButton.style.color = '#999';
                    copyButton.style.cursor = 'pointer';

                    pre.appendChild(copyButton);

                    // 检查是否是 HTML 代码，添加预览按钮
                    if (isHtmlCode(codeContent)) {
                        const previewButton = document.createElement('button');
                        previewButton.classList.add('code-preview-btn');
                        previewButton.innerHTML = '<i class="mdui-icon material-icons">visibility</i>';
                        previewButton.title = '预览 HTML';
                        previewButton.addEventListener('click', () => {
                            showHtmlPreview(codeContent);
                        });
                        previewButton.style.position = 'absolute';
                        previewButton.style.top = '5px';
                        previewButton.style.right = '35px';
                        previewButton.style.background = 'none';
                        previewButton.style.border = 'none';
                        previewButton.style.color = '#999';
                        previewButton.style.cursor = 'pointer';

                        pre.appendChild(previewButton);
                    }
                });
            }

            chatMessages.appendChild(messageDiv);

            // 滚动到底部
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // 更新消息元素
        function updateMessageElement(msg) {
            const messageDiv = chatContainer.querySelector(`#message-${msg.id}`);
            if (messageDiv) {
                const contentDiv = messageDiv.querySelector('.content');
                let contentHTML = parseMarkdown(msg.content);

                if (msg.loading) {
                    // 在消息末尾添加闪烁光标
                    contentHTML += '<span class="blinking-cursor">_</span>';
                }

                contentDiv.innerHTML = contentHTML;
                messageDiv.classList.remove('loading-message');

                // 重新绑定事件和语法高亮
                contentDiv.querySelectorAll('pre').forEach((pre) => {
                    const code = pre.querySelector('code');
                    const codeContent = code.innerText;
                    const language = code.className || '';

                    // 语法高亮
                    hljs.highlightElement(code);

                    // 创建复制按钮
                    const copyButton = document.createElement('button');
                    copyButton.classList.add('code-copy-btn');
                    copyButton.innerHTML = '<i class="mdui-icon material-icons">content_copy</i>';
                    copyButton.title = '复制代码';
                    copyButton.addEventListener('click', () => {
                        navigator.clipboard.writeText(codeContent).then(() => {
                            mdui.snackbar({ message: '代码已复制到剪贴板' });
                        }).catch(err => {
                            mdui.snackbar({ message: '复制失败，请手动复制' });
                        });
                    });
                    copyButton.style.position = 'absolute';
                    copyButton.style.top = '5px';
                    copyButton.style.right = '5px';
                    copyButton.style.background = 'none';
                    copyButton.style.border = 'none';
                    copyButton.style.color = '#999';
                    copyButton.style.cursor = 'pointer';

                    pre.appendChild(copyButton);

                    // 检查是否是 HTML 代码，添加预览按钮
                    if (isHtmlCode(codeContent)) {
                        const previewButton = document.createElement('button');
                        previewButton.classList.add('code-preview-btn');
                        previewButton.innerHTML = '<i class="mdui-icon material-icons">visibility</i>';
                        previewButton.title = '预览 HTML';
                        previewButton.addEventListener('click', () => {
                            showHtmlPreview(codeContent);
                        });
                        previewButton.style.position = 'absolute';
                        previewButton.style.top = '5px';
                        previewButton.style.right = '35px';
                        previewButton.style.background = 'none';
                        previewButton.style.border = 'none';
                        previewButton.style.color = '#999';
                        previewButton.style.cursor = 'pointer';

                        pre.appendChild(previewButton);
                    }
                });
            }
        }

        // 删除消息
        function deleteMessage(msgId) {
            const currentChat = getChatById(currentChatId);
            if (currentChat) {
                const index = currentChat.messages.findIndex(msg => msg.id === msgId);
                if (index !== -1) {
                    currentChat.messages.splice(index, 1);
                    saveChats();

                    // 移除消息元素
                    const messageDiv = chatContainer.querySelector(`#message-${msgId}`);
                    if (messageDiv) {
                        messageDiv.remove();
                    }
                }
            }
        }

        // 编辑消息
        function editMessage(msgId) {
            const currentChat = getChatById(currentChatId);
            if (currentChat) {
                const msg = currentChat.messages.find(m => m.id === msgId);

                // 使用 mdui 的对话框
                const dialogContent = `
                    <div class="mdui-dialog" id="editMessageDialog">
                        <div class="mdui-dialog-title">编辑消息</div>
                        <div class="mdui-dialog-content">
                            <div class="mdui-textfield">
                                <textarea class="mdui-textfield-input" id="editMessageContent" rows="5">${msg.content}</textarea>
                            </div>
                        </div>
                        <div class="mdui-dialog-actions">
                            <button class="mdui-btn mdui-ripple" mdui-dialog-close>取消</button>
                            <button class="mdui-btn mdui-ripple" id="saveEditedMessage">保存</button>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', dialogContent);
                const dialog = new mdui.Dialog('#editMessageDialog');
                dialog.open();

                chatContainer.querySelector('#saveEditedMessage').addEventListener('click', () => {
                    const newContent = chatContainer.querySelector('#editMessageContent').value;
                    msg.content = newContent;
                    saveChats();
                    updateMessageElement(msg);
                    dialog.close();
                    document.getElementById('editMessageDialog').remove();
                });

                dialog.$element.on('closed.mdui.dialog', function () {
                    document.getElementById('editMessageDialog').remove();
                });
            }
        }

        // 复制消息
        function copyMessage(content) {
            navigator.clipboard.writeText(content).then(() => {
                mdui.snackbar({ message: '消息已复制到剪贴板' });
            }).catch(err => {
                mdui.snackbar({ message: '复制失败，请手动复制' });
            });
        }

        // 重新生成消息
        async function regenerateMessage(msgId) {
            const currentChat = getChatById(currentChatId);
            if (!currentChat) return;

            const index = currentChat.messages.findIndex(msg => msg.id === msgId);
            if (index === -1) return;

            // 删除原消息并渲染
            currentChat.messages.splice(index, 1);
            saveChats();
            renderChat();

            // 显示加载中的消息
            const loadingMsg = {
                id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
                role: 'assistant',
                loading: true,
                content: ''
            };
            currentChat.messages.push(loadingMsg);
            saveChats();
            appendMessage(loadingMsg);

            // 调用 AI 接口获取新的回复
            try {
                const response = await fetch(apiBaseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${apiKey}`
                    },
                    body: JSON.stringify({
                        model: selectedModel,
                        messages: currentChat.messages.filter(msg => !msg.loading),
                        functions: serverFunctions(),
                        function_call: "auto",
                        stream: true
                    })
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`请求失败，状态码：${response.status}，错误信息：${errorText}`);
                }

                await handleStreamedResponse(response, loadingMsg);

            } catch (error) {
                const errorMsg = {
                    id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
                    role: 'assistant',
                    content: `请求失败: ${error.message}`
                };
                currentChat.messages.push(errorMsg);
                saveChats();
                appendMessage(errorMsg);
                console.error('请求失败:', error);
            }
        }

        // 简单的 Markdown 解析
        function parseMarkdown(text) {
            if (!text) return '';

            // 配置 marked.js
            marked.setOptions({
                breaks: true, // 自动将换行符转换为 <br>
                highlight: function (code, lang) {
                    // 使用 highlight.js 渲染代码块
                    const language = hljs.getLanguage(lang) ? lang : 'plaintext';
                    return hljs.highlight(code, { language }).value;
                },
            });

            // 使用 marked.parse 解析 Markdown
            return marked.parse(text);
        }

        // 发送消息
        async function sendMessage() {
            const content = userInput.value.trim();
            if (!content || !currentChatId) return;
            addMessage({ role: 'user', content });
            userInput.value = '';
            await getAIResponse();
        }

        // 自动生成聊天标题
        function generateChatTitle() {
            const currentChat = getChatById(currentChatId);
            if (currentChat) {
                // 取最新的用户消息作为标题
                for (let i = currentChat.messages.length - 1; i >= 0; i--) {
                    if (currentChat.messages[i].role === 'user') {
                        currentChat.name = currentChat.messages[i].content.slice(0, 20);
                        break;
                    }
                }
                saveChats();
                renderChatList();
            }
        }

        // 获取 AI 回复
        async function getAIResponse() {
            const currentChat = getChatById(currentChatId);
            if (!currentChat) return;

            // 显示加载中的消息
            const loadingMsg = {
                id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
                role: 'assistant',
                loading: true,
                content: ''
            };
            currentChat.messages.push(loadingMsg);
            saveChats();
            appendMessage(loadingMsg);

            try {
                const functions = serverFunctions();

                const payload = {
                    model: selectedModel,
                    messages: currentChat.messages.filter(msg => !msg.loading),
                    functions: functions.length > 0 ? functions : undefined,
                    function_call: functions.length > 0 ? "auto" : { name: "none" },
                    stream: true
                };

                const response = await fetch(apiBaseUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Authorization": `Bearer ${apiKey}`
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`请求失败：${response.status}，错误信息：${errorText}`);
                }

                await handleStreamedResponse(response, loadingMsg);

            } catch (error) {
                const errorMsg = {
                    id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
                    role: 'assistant',
                    content: `请求失败: ${error.message}`
                };
                currentChat.messages.push(errorMsg);
                saveChats();
                appendMessage(errorMsg);
                console.error("请求失败:", error);
            }
        }

        // 处理流式响应
        async function handleStreamedResponse(response, loadingMsg) {
            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let done = false;
            let assistantMessage = '';
            let functionCall = null;
            let assistantMsg = loadingMsg;
            assistantMsg.content = '';
            // 不要在这里将 loading 设置为 false

            const currentChat = getChatById(currentChatId);
            if (!currentChat) return;

            while (!done) {
                const { value, done: readerDone } = await reader.read();
                done = readerDone;
                if (value) {
                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n').filter(line => line.trim() !== '');
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const dataStr = line.slice(6);
                            if (dataStr === '[DONE]') {
                                done = true;
                                break;
                            }
                            try {
                                const json = JSON.parse(dataStr);

                                // 检查是否存在错误
                                if (json.error) {
                                    throw new Error(json.error.message);
                                }

                                if (json.choices && json.choices[0]) {
                                    const delta = json.choices[0].delta;

                                    if (delta.content) {
                                        assistantMessage += delta.content;

                                        assistantMsg.content = assistantMessage;
                                        assistantMsg.loading = true; // 保持 loading 为 true
                                        saveChats();
                                        updateMessageElement(assistantMsg);
                                    } else if (delta.function_call) {
                                        // 收集函数调用信息
                                        if (!functionCall) {
                                            functionCall = {
                                                name: delta.function_call.name || '',
                                                arguments: delta.function_call.arguments || ''
                                            };
                                        } else {
                                            functionCall.arguments += delta.function_call.arguments || '';
                                        }

                                        // 显示函数调用提示
                                        showFunctionCallTip(functionCall.name);
                                    }
                                } else {
                                    console.error('Unexpected JSON structure:', json);
                                }
                            } catch (e) {
                                console.error('解析流数据出错：', e);
                                // 添加错误消息
                                const errorMsg = {
                                    id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
                                    role: 'assistant',
                                    content: `请求失败: ${e.message}`
                                };
                                currentChat.messages.push(errorMsg);
                                saveChats();
                                appendMessage(errorMsg);
                                return;
                            }
                        }
                    }
                }
            }

            // 如果助手消息内容为空且存在函数调用，移除空白消息
            if (assistantMessage.trim() === '' && functionCall) {
                // 从聊天记录中移除助手的空消息
                const index = currentChat.messages.findIndex(msg => msg.id === assistantMsg.id);
                if (index !== -1) {
                    currentChat.messages.splice(index, 1);
                }
                // 从界面中移除
                const messageDiv = chatContainer.querySelector(`#message-${assistantMsg.id}`);
                if (messageDiv) {
                    messageDiv.remove();
                }
                saveChats();
            } else {
                // 流式响应处理完毕后，移除光标
                assistantMsg.loading = false;
                saveChats();
                updateMessageElement(assistantMsg);
            }

            // 如果存在函数调用，处理函数调用
            if (functionCall) {
                try {
                    showFunctionCallTip(functionCall.name);
                    const funcResponse = await handleFunctionCall(functionCall);
                    
                    // 添加函数调用结果到聊天记录
                    currentChat.messages.push(funcResponse);
                    
                    saveChats();
                    appendMessage(funcResponse);

                    // 隐藏函数调用提示
                    // 延迟 2 秒后执行 hideFunctionCallTip()
                    setTimeout(() => {
                        hideFunctionCallTip();
                    }, 2000);


                    // 继续请求 AI 回复
                    await getAIResponse();
                } catch (error) {
                    console.error('函数调用出错：', error);
                    const errorMsg = {
                        id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
                        role: 'assistant',
                        content: `函数调用失败: ${error.message}`
                    };
                    currentChat.messages.push(errorMsg);
                    saveChats();
                    appendMessage(errorMsg);
                    // 隐藏函数调用提示
                    // 延迟 2 秒后执行 hideFunctionCallTip()
                    setTimeout(() => {
                        hideFunctionCallTip();
                    }, 2000);
                }
            }
        }

        // 显示右键菜单
        function showContextMenu(x, y) {
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            contextMenu.style.display = 'block';
        }

        // 隐藏右键菜单
        function hideContextMenu() {
            contextMenu.style.display = 'none';
        }

        // 复制消息（用于右键菜单）
        function copyClickedMessage() {
            navigator.clipboard.writeText(clickedMessageContent).then(() => {
                mdui.snackbar({ message: '消息已复制到剪贴板' });
            }).catch(err => {
                mdui.snackbar({ message: '复制失败，请手动复制' });
            });
            hideContextMenu();
        }

        // 点击复制消息菜单项
        copyMessageItem.addEventListener('click', copyClickedMessage);

        // 全局点击事件，隐藏右键菜单
        document.addEventListener('click', function (e) {
            if (contextMenu.style.display === 'block') {
                hideContextMenu();
            }
        });

        // 显示函数调用提示框
        function showFunctionCallTip(functionName) {
            functionCallTip.textContent = `GPT正在调用${functionName}操作`;
            functionCallTip.style.display = 'block';
        }

        // 隐藏函数调用提示框
        function hideFunctionCallTip() {
            functionCallTip.style.display = 'none';
        }

        // 定义服务器管理的函数
        function serverFunctions() {
            return [
                {
                    name: "getGptStatus",
                    description: "获取ChatGPT状态",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "restartServer",
                    description: "重启服务器",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "searchWeb",
                    description: "在网络上搜索信息",
                    parameters: {
                        type: "object",
                        properties: {
                            query: { type: "string", description: "要搜索的关键词" }
                        },
                        required: ["query"]
                    }
                },
                // 新增的服务器控制功能
                {
                    name: "startHost",
                    description: "启动主机",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "closeHost",
                    description: "关闭主机",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "powerHost",
                    description: "断电主机",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "createSnapshot",
                    description: "创建主机快照",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "createBackup",
                    description: "创建主机备份",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "addFirewall",
                    description: "添加防火墙策略",
                    parameters: {
                        type: "object",
                        properties: {
                            policy: { type: "string", description: "防火墙策略内容" }
                        },
                        required: ["policy"]
                    }
                },
                {
                    name: "link",
                    description: "建立普通连接",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "vnc",
                    description: "获取VNC连接",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                },
                {
                    name: "lowvnc",
                    description: "获取低带宽VNC连接",
                    parameters: {
                        type: "object",
                        properties: {},
                        required: []
                    }
                }
            ];
        }

        // 处理函数调用
        async function handleFunctionCall(function_call) {
            const { name, arguments: args } = function_call;
           
            if (!name) {
                console.error("Function call is missing 'name':", function_call);
                return { role: "function", name: "unknown", content: "未定义的函数调用" };
            }

            let result = "";
            try {
                switch (name) {
                    case "getGptStatus":
                        // 具体逻辑
                        result = "1";
                        break;
                    case "restartServer":
                        // 具体逻辑
                        result = "服务器正在重启...";
                        await new Promise(resolve => setTimeout(resolve, 2000)); // 模拟重启过程
                        result = "服务器已成功重启。";
                        break;
                    case "searchWeb":
                        let query = "";
                        try {
                            query = JSON.parse(args || "{}").query || "未知关键词";
                        } catch (e) {
                            console.error("解析函数参数错误：", e);
                            query = "未知关键词";
                        }

                        // 简单搜索逻辑，可以集成实际的搜索API
                        result = `搜索结果：关于 "${query}" 的最新信息。`;
                        break;
                    
                    // 新增的服务器控制功能
                    case "startHost":
                        result = await startHost();
                        break;
                    case "closeHost":
                        result = await closeHost();
                        break;
                    case "powerHost":
                        result = await powerHost();
                        break;
                    case "createSnapshot":
                        result = await createSnapshot();
                        break;
                    case "createBackup":
                        result = await createBackup();
                        break;
                    case "addFirewall":
                        let policy = "";
                        try {
                            policy = JSON.parse(args || "{}").policy || "默认策略";
                        } catch (e) {
                            console.error("解析函数参数错误：", e);
                            policy = "默认策略";
                        }
                        result = await addFirewall(policy);
                        break;
                    case "link":
                        result = await link();
                        break;
                    case "vnc":
                        result = await vnc();
                        break;
                    case "lowvnc":
                        result = await lowvnc();
                        break;
                    default:
                        result = `未知函数: ${name}`;
                }
            } catch (error) {
                console.error("Function execution error:", error);
                result = `函数执行失败: ${error.message}`;
            }
            
            return {
                role: "function",
                name,
                content: result
            };
        }

        // 导出聊天记录
        function exportChat() {
            if (!currentChatId) {
                mdui.snackbar({ message: '请选择一个聊天会话进行导出' });
                return;
            }
            const currentChat = getChatById(currentChatId);
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(currentChat, null, 2));
            const downloadAnchor = document.createElement('a');
            downloadAnchor.setAttribute("href", dataStr);
            downloadAnchor.setAttribute("download", `${currentChat.name}_chat_history.json`);
            document.body.appendChild(downloadAnchor);
            downloadAnchor.click();
            downloadAnchor.remove();
        }

        // 导入聊天记录
        function importChat(file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const importedChat = JSON.parse(e.target.result);
                    if (importedChat.id && importedChat.name && Array.isArray(importedChat.messages)) {
                        chats.push(importedChat);
                        currentChatId = importedChat.id;
                        saveChats();
                        renderChatList();
                        renderChat();
                        mdui.snackbar({ message: '聊天记录导入成功' });
                    } else {
                        throw new Error("无效的聊天记录格式");
                    }
                } catch (error) {
                    mdui.snackbar({ message: `导入失败: ${error.message}` });
                }
            };
            reader.readAsText(file);
        }

        // 判断是否是 HTML 代码
        function isHtmlCode(code) {
            return /<\/?[a-z][\s\S]*>/i.test(code);
        }

        // 显示 HTML 预览
        function showHtmlPreview(htmlContent) {
            const dialogHtml = `
                <div class="mdui-dialog" id="htmlPreviewDialog">
                    <div class="mdui-dialog-title">HTML 预览</div>
                    <div class="mdui-dialog-content">
                        <iframe id="htmlPreviewFrame" style="width:100%; height:400px; border:none;"></iframe>
                    </div>
                    <div class="mdui-dialog-actions">
                        <button class="mdui-btn mdui-ripple" mdui-dialog-close>关闭</button>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', dialogHtml);
            const dialog = new mdui.Dialog('#htmlPreviewDialog');
            dialog.open();

            // 将 HTML 内容写入 iframe
            const iframe = document.getElementById('htmlPreviewFrame');
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(htmlContent);
            iframeDoc.close();

            // 监听对话框关闭事件，移除对话框元素
            dialog.$element.on('closed.mdui.dialog', function () {
                dialog.$element.remove();
            });
        }

        // 语音输入和输出
        let recognizing = false;
        let recognition;
        let synth = window.speechSynthesis;

        function initSpeechRecognition() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                mdui.snackbar({ message: '浏览器不支持语音识别' });
                return;
            }
            recognition = new SpeechRecognition();
            recognition.lang = 'zh-CN';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;

            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                userInput.value += transcript;
            };

            recognition.onerror = (event) => {
                mdui.snackbar({ message: `语音识别错误: ${event.error}` });
            };
        }

        function speak(text) {
            if (!synth) return;
            const utterThis = new SpeechSynthesisUtterance(text);
            utterThis.lang = 'zh-CN';
            synth.speak(utterThis);
        }

        // 事件绑定
        sendBtn.addEventListener('click', sendMessage);
        userInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        voiceBtn.addEventListener('click', () => {
            if (!recognition) {
                initSpeechRecognition();
            }
            if (recognizing) {
                recognition.stop();
                recognizing = false;
                voiceBtn.innerHTML = '<i class="mdui-icon material-icons">mic</i>';
            } else {
                recognition.start();
                recognizing = true;
                voiceBtn.innerHTML = '<i class="mdui-icon material-icons">mic_off</i>';
            }
        });

        // 文件上传
        fileUploadBtn.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', () => {
            const files = fileInput.files;
            if (files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    // 处理文件，这里仅显示文件名
                    addMessage({ role: 'user', content: `已上传文件：${file.name}` });
                }
                fileInput.value = '';
            }
        });

        exportBtn.addEventListener('click', exportChat);

        importBtn.addEventListener('click', () => {
            importFile.click();
        });

        importFile.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                importChat(e.target.files[0]);
                importFile.value = '';
            }
        });

        addChatBtn.addEventListener('click', () => {
            const chatName = prompt('请输入新的聊天会话名称', '新聊天');
            if (chatName) {
                addNewChat(chatName);
            }
        });

        // 模型选择器
        // 由于模型选择器在原HTML中没有被封装到组件内，这里需要动态创建
        // 或者确保在嵌入时提供相应的选择器
        // 这里假设模型选择器已包含在聊天组件的HTML中
        // 如果没有，请根据需要进行调整

        // 设置初始值
        const modelSelectorElement = document.createElement('select');
        modelSelectorElement.id = 'modelSelector';
        modelSelectorElement.classList.add('mdui-select');
        modelSelectorElement.innerHTML = `
            <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
            <option value="gpt-3.5-turbo-0613">GPT-3.5 Turbo-0613</option>
            <option value="gpt-4">GPT-4</option>
        `;
        modelSelectorElement.value = selectedModel;
        chatContainer.querySelector('.chat-toolbar').appendChild(modelSelectorElement);
        modelSelectorElement.addEventListener('change', () => {
            selectedModel = modelSelectorElement.value;
            localStorage.setItem('selectedModel', selectedModel);
        });

        // 返回底部按钮
        scrollToBottomBtn.addEventListener('click', () => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });

        chatMessages.addEventListener('scroll', () => {
            if (chatMessages.scrollHeight - chatMessages.scrollTop > chatMessages.clientHeight + 100) {
                scrollToBottomBtn.style.display = 'block';
            } else {
                scrollToBottomBtn.style.display = 'none';
            }
        });

        // 配置对话框
        const configDialog = new mdui.Dialog(chatContainer.querySelector('#configDialog'), {
            modal: true,
            history: false
        });

        // 配置按钮事件
        const configBtn = chatContainer.querySelector('#configBtn');
        configBtn.addEventListener('click', () => {
            chatContainer.querySelector('#apiBaseUrl').value = apiBaseUrl;
            chatContainer.querySelector('#apiKey').value = apiKey;
            configDialog.open();
        });

        // 保存配置
        chatContainer.querySelector('#saveConfig').addEventListener('click', () => {
            apiBaseUrl = chatContainer.querySelector('#apiBaseUrl').value.trim();
            apiKey = chatContainer.querySelector('#apiKey').value.trim();
            localStorage.setItem('apiBaseUrl', apiBaseUrl);
            localStorage.setItem('apiKey', apiKey);
            configDialog.close();
            mdui.snackbar({ message: '配置已保存' });
        });

        // 初始化
        function initialize() {
            if (chats.length === 0) {
                addNewChat('新聊天');
            } else if (!currentChatId) {
                currentChatId = chats[0].id;
                saveChats();
            }
            renderChatList();
            renderChat();
        }

        initialize();
    }

    // Expose the initialization function to the global scope
    window.initializeEmbeddedChatWidget = initializeChatWidget;
})();

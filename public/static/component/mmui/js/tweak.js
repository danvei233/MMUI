// 初始化一个对象来存储字段数据
const danvei = {
    field: {}
};

// 获取所有class为"danvei-form"的元素
const forms = document.querySelectorAll('.danvei-form');

// 为每个表单中的每个input和select元素添加事件监听器
forms.forEach(form => {
    const elements = form.querySelectorAll('input, select');
    elements.forEach(element => {
        // 初始化data.field对象
        if (element.name) {
            danvei.field[element.name] = element.value;
        }

        // 绑定input和change事件监听器
        element.addEventListener(element.tagName === 'SELECT' ? 'change' : 'input', function() {
            // 当元素的值发生变化时，更新data对象
            danvei.field[this.name] = this.value;
            
        });
    });
});

    function isDarkMode() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    
    document.addEventListener('DOMContentLoaded', function () {
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const glassModeToggle = document.getElementById('glass-mode-toggle');
        const body = document.body;
    
        // 检查 localStorage 中是否有暗模式的设置
        if (localStorage.getItem('darkMode') === 'enabled' || isDarkMode()) {
            body.classList.add('dark-mode');
        }
    
        // 检查 localStorage 中是否有毛玻璃模式的设置
        if (localStorage.getItem('glassMode') === 'enabled' && !isDarkMode()) {
            body.classList.add('glass-mode');
        }
    
        // 切换暗模式
        darkModeToggle.addEventListener('click', function () {
            document.startViewTransition(() => {
                // 切换暗模式
                body.classList.toggle('dark-mode');
                body.classList.remove('glass-mode');
                localStorage.setItem('glassMode', 'disabled');
    
                // 根据当前状态更新 localStorage
                if (body.classList.contains('dark-mode')) {
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    localStorage.setItem('darkMode', 'disabled');
                }
            });
        });
    
        // 切换毛玻璃模式
        glassModeToggle.addEventListener('click', function () {
            document.startViewTransition(() => {
                body.classList.toggle('glass-mode');
                body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'disabled');
    
                // 更新 localStorage
                if (body.classList.contains('glass-mode')) {
                    localStorage.setItem('glassMode', 'enabled');
                } else {
                    localStorage.setItem('glassMode', 'disabled');
                }
            });
        });
    });
    
// script.js
// script.js
document.addEventListener('DOMContentLoaded', function() {
    var loadingElement = document.querySelector('.page-loading');

    // 创建一个观察者实例并传入回调函数
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === "class" && loadingElement.classList.contains('hidden')) {
                // 当 page-loading 元素隐藏时触发所有动画
                document.querySelectorAll('.animate__animated').forEach(function(element) {
                    animateCSS(element, 'zoomIn'); // 示例使用 'bounce' 动画
                });
                observer.disconnect(); // 停止观察
            }
        });
    });

    // 配置观察选项:
    var config = { attributes: true };

    // 传入目标节点和观察选项
    observer.observe(loadingElement, config);
});

// 动画播放函数
function animateCSS(element, animationName, callback) {
    const animationClass = 'animate__' + animationName;
    element.classList.remove(animationClass); // 先移除类以便重新触发动画
    element.offsetWidth; // 触发重排来重置动画
    element.classList.add(animationClass);

    function handleAnimationEnd() {
        element.classList.remove(animationClass);
        element.removeEventListener('animationend', handleAnimationEnd);

        if (typeof callback === 'function') callback();
    }

    element.addEventListener('animationend', handleAnimationEnd);
}


document.addEventListener('DOMContentLoaded', function() {
 

  const eggButton = document.getElementById('eggButton');
  const eggWeb = document.getElementById('eggweb');
 

  if (!eggButton) {
    console.error('eggButton element not found');
    return;
  }

  if (!eggWeb) {
    console.error('eggWeb element not found');
    return;
  }

  const buttonTexts = [
  '你触发了隐藏彩蛋！',
  '再点一次看看会发生什么？',
  '秘密通道已开启！',
  '继续探索，说不定有惊喜～',
  '真是个好奇宝宝！',
  '小心，再点我就要溜了！',
  '哇哦，你太厉害了！',
  '探索的脚步永不停歇！',
  '这是专属你的彩蛋！',
  '支持一下，谢谢你！'
];

//觉得这个是广告的可以**自己删掉**
const websites = [
    
    
'https://game.share888.top/yxmb/5/index.html',
'https://www.arealme.com/coreball/cn/',
'http://farter.cn/tetr.js/',
'https://miku.aiursoft.cn/',
'https://theuselessweb.com/',
'http://gogoame.sumbioun.com/',
'https://chishenme.xyz/',
'https://pf.bigpixel.cn/zh-CN.html'
   
   
   
];


 

  eggButton.addEventListener('click', function() {
    
    const randomTextIndex = Math.floor(Math.random() * buttonTexts.length);
  
    eggButton.textContent = buttonTexts[randomTextIndex];
   

    const randomWebsiteIndex = Math.floor(Math.random() * websites.length);
 
    eggWeb.src = websites[randomWebsiteIndex];
    console.log('eggWeb src changed to:', eggWeb.src);
  });
});


let $ = layui.jquery;
    document.addEventListener('DOMContentLoaded', function() {
        mdui.mutation();
    
    
        
        // 获取所有的 <li> 元素
        var listItems = document.querySelectorAll('.mdui-list-item.mdui-ripple');
        
        // 确保找到 <li> 元素
        if (listItems.length > 0) {
      
        } else {
            console.error('No <li> elements found');
        }
    
        // 获取 mdui tabs 实例
        var tabs = new mdui.Tab('#wd');
    
        // 遍历每个 <li> 元素并注册点击事件
        listItems.forEach(function(li) {
            li.addEventListener('click', function() {
                // 获取 <li> 的 value 值
                var value = li.getAttribute('value');
                
              
    
                // 切换到对应的 tab
                if (value !== null) {
                    tabs.show(Number(value));
                    listItems.forEach(function(item) {
                        if (item !== li) {  // 如果不是当前被点击的那个 <li>，移除类
                            item.classList.remove('mdui-list-item-active');
                        }
                    });
                    
                    // 给当前点击的 <li> 添加 .mdui-list-item-active 类
                    li.classList.add('mdui-list-item-active');
                }
                
    
                // 只移除未被点击的 <li> 元素的 .mdui-list-item-active 类
                
            }, { passive: true }); // 将事件监听器标记为被动
        });
    });

// 初始化 Tab 组件



// 如果需要程序化地切换到第10个标签页





document.addEventListener('DOMContentLoaded', function () {
    // 判断是否第一次运行
    if (!localStorage.getItem('firstrun')) {
        // 第一次运行时，不折叠所有 collapse-item
        localStorage.setItem('firstrun', 'true');
        
        // 非手机设备，默认打开所有 collapse-item
        document.querySelectorAll('.mdui-collapse-item').forEach(function (item) {
            item.classList.add('mdui-collapse-item-open');
        });
    } else {
        var isMobile = window.matchMedia("(max-width: 768px)").matches;

        if (isMobile) {
            // 手机设备，默认关闭所有 collapse-item
            document.querySelectorAll('.mdui-collapse-item').forEach(function (item) {
                item.classList.remove('mdui-collapse-item-open');
            });
        } else {
            // 非手机设备，默认打开所有 collapse-item
            document.querySelectorAll('.mdui-collapse-item').forEach(function (item) {
                item.classList.add('mdui-collapse-item-open');
            });
        }
    }
});

// 监听窗口大小变化以动态调整 collapse-item 状态
window.addEventListener('resize', function () {
    var isMobile = window.matchMedia("(max-width: 768px)").matches;

    if (isMobile) {
        document.querySelectorAll('.mdui-collapse-item').forEach(function (item) {
            item.classList.remove('mdui-collapse-item-open');
        });
    } else {
        document.querySelectorAll('.mdui-collapse-item').forEach(function (item) {
            item.classList.add('mdui-collapse-item-open');
        });
    }
});



    // 模拟 layer 对象
    var layer = layer || {};

    // 重写 layer.msg 函数
    layer.msg = function (message, options) {
        options = options || {};
        var position = options.position || 'right-bottom';
        
        mdui.snackbar({
            message: message,
            position: 'right-bottom',
            timeout: options.timeout || 4000  // 默认 4 秒消失
        });
    };

    // 示例函数，调用 layer.msg
    function showMessage() {
        layer.msg('这是一个消息提示', { position: 'right-bottom', timeout: 3000 });
    }



document.addEventListener('DOMContentLoaded', function() {
    // 假设加载过程需要 3 秒钟
    setTimeout(function() {
        const loadingElement = document.querySelector('.page-loading');
        loadingElement.classList.add('hidden');
    }, 3000);
});
document.getElementById('downloadBtn').addEventListener('click', function() {
    // 静态的 RDP 文件内容
    const rdpContent = [
        "full address:s:{if $host.is_nat==1}{$network['eth1'][0]['public_ip']} {else}{$network['eth1'][0]['ip']}{/if}{if $host.is_nat==1}:{$port}{/if}",
        "username:s:administrator",
        "prompt for credentials:i:1"
    ].join('\r\n');

    // 创建 Blob 对象
    const blob = new Blob([rdpContent], { type: 'application/rdp' });

    // 创建下载链接并触发下载
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'connection.rdp';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url); // 清除内存中的引用
    copyToClipA('{$host.os_password}');
    
});
  
var hostId = '{$hostid}'; // 请替换为实际的 hostId

// 通用函数，用于发送 POST 请求并处理响应
function getCidAndRedirect(url) {
    // 构建请求的 URL，根据是否增强模式调整参数
    const rootUrl = "{$rootUrl}";
const hostid = "{$hostid}";


    // 发送 POST 请求'
    {if 1==1}
    $.post(url, { hostid: hostId}, function(response) {
        if (response.code === 200) {
            var cid = response.data;
            var redirectUrl = `https://netmc.icu:8099/Myrtille/?__EVENTTARGET=&__EVENTARGUMENT=&cid=${cid}&connect=Connect%21`;
            window.open(redirectUrl, '_blank'); // 在新标签页中打开
        } else {
            console.error('请求失败:', response.msg);
            alert(`请求失败: ${response.msg}`);
        }
    }, 'json')
    .fail(function(jqXHR, textStatus, errorThrown) {
        console.error('POST 请求错误:', textStatus, errorThrown);
        alert(`发生错误: ${textStatus}`);
    });
    {else}
    $.post(rootUrl+'vnc_host',{hostid:hostid},function (data) {
        layer.closeAll('loading');
        if (data.code == 1) {
            window.open(data.url);
            return false;
        } else {
            layer.msg(data.msg, {icon: 2});
        }
    },'json')
    {/if}
}


// link 函数
function link() {
    const url = rootUrl +'CreateNormalConnect';
    getCidAndRedirect(url);
}

// vnc 函数
function vnc() {
    const url = rootUrl + 'getVNC';
    getCidAndRedirect(url);
}

// lowvnc 函数
function lowvnc() {
    const url = rootUrl +'getVNC?IsEnhancedMode=false';
    getCidAndRedirect(url);
}


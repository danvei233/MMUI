class MmuiLoading {
  constructor(options = {}) {
    const defaults = {
      text: '执行中',
      color: '#2d8cf0',
      showText: true,
      zIndex: 9999
    };
    this.config = {...defaults, ...options};
    
    this.initDOM();
    this.appendStyle();
  }

  initDOM() {
    this.container = document.createElement('div');
    this.container.className = 'mmui-loading';
    this.container.style.zIndex = this.config.zIndex;

    const content = `
      <div class="mmui-loading__content">
        <div class="mmui-loading__ring" style="border-color: ${this.config.color}"></div>
        ${this.config.showText ? `<div class="mmui-loading__text">${this.config.text}</div>` : ''}
      </div>
    `;
    
    this.container.innerHTML = content;
    document.body.appendChild(this.container);
  }

  appendStyle() {
    if(!document.getElementById('mmui-loading-styles')) {
      const link = document.createElement('link');
      link.id = 'mmui-loading-styles';
      link.rel = 'stylesheet';
      link.href = '/static/component/mmui/css/loading.css';
      document.head.appendChild(link);
    }
  }

  show() {
    this.container.style.display = 'block';
  }

  hide() {
    this.container.style.display = 'none';
  }

  destroy() {
    this.container.remove();
  }
}

// 示例用法：
// const loader = new MmuiLoading();
// loader.show();
// setTimeout(() => loader.hide(), 3000);

<template>
  <a-spin :spinning="store.loading && !store.ready">
    <a-layout class="mmui-app">
      <div class="mmui-bg" aria-hidden="true">
        <img
          alt="background"
          src="https://mdn.alipayobjects.com/yuyan_qk0oxh/afts/img/D2LWSqNny4sAAAAAAAAAAAAAFl94AQBr"
        />
        <img
          alt="background"
          src="https://mdn.alipayobjects.com/yuyan_qk0oxh/afts/img/C2TWRpJpiC0AAAAAAAAAAAAAFl94AQBr"
        />
        <img
          alt="background"
          src="https://mdn.alipayobjects.com/yuyan_qk0oxh/afts/img/F6vSTbj8KpYAAAAAAAAAAAAAFl94AQBr"
        />
      </div>

      <MmuiHeader
        :title="pageTitle"
        :logo="consoleLogo"
        :is-desktop="isDesktopViewport"
        :sidebar-collapsed="desktopSidebarCollapsed"
        @toggle-sidebar="handleMenuToggle"
      />

      <a-layout class="mmui-shell">
        <a-layout-sider
          class="mmui-sidebar"
          :class="{ 'is-open': isDesktopViewport || desktopSidebarOpen, 'is-mobile': !isDesktopViewport, 'is-collapsed': desktopSidebarCollapsed && isDesktopViewport }"
          :width="256"
          theme="light"
        >
          <MmuiSidebar
            :active-key="activePageKey"
            :collapsed="desktopSidebarCollapsed && isDesktopViewport"
            :title="consoleTitle"
            :logo="consoleLogo"
            @select="handlePageSelect"
            @toggle-sidebar="handleMenuToggle"
          />
        </a-layout-sider>

        <a-layout-content
          class="mmui-main"
          :class="{ 'is-sidebar-open': isDesktopViewport, 'is-sidebar-collapsed': desktopSidebarCollapsed && isDesktopViewport }"
        >
          <div class="mmui-page-width">
            <Transition name="mmui-page-tab" mode="out-in" appear :duration="{ enter: 640, leave: 70 }">
              <div v-if="activePageKey === 'panel'" key="panel" class="dashboard-page">
                <section class="home-card dashboard-page__topbar">
                  <div class="dashboard-page__topbar-main">
                    <div class="dashboard-page__topbar-info">
                      <div class="dashboard-page__topbar-icon">
                        <component :is="osIcon" />
                      </div>
                      <div class="dashboard-page__topbar-copy">
                        <div class="dashboard-page__topbar-title-row">
                          <div class="dashboard-page__topbar-name">{{ store.host.name || 'ECS' }}</div>
                          <a-tag
                            class="dashboard-page__topbar-tag dashboard-page__topbar-tag--status"
                            :class="`is-${hostStatusTone}`"
                          >
                            {{ store.host.status || '未知' }}
                          </a-tag>
                        </div>
                        <div class="dashboard-page__topbar-meta">
                          <span class="dashboard-page__topbar-location">
                            <EnvironmentOutlined />
                            <span>{{ store.host.areaName || '-' }}</span>
                          </span>
                          <span class="dashboard-page__topbar-separator">·</span>
                          <span class="dashboard-page__topbar-ip">IPv4 {{ remoteHost }}</span>
                          <a-button class="dashboard-page__copy-btn dashboard-page__topbar-copy-btn" type="text" size="small" @click="copyValue(remoteHost, 'IPv4')">
                            <CopyOutlined />
                          </a-button>
                        </div>
                      </div>
                    </div>

                    <div class="dashboard-page__topbar-actions">
                      <a-button class="dashboard-page__remote-connect-btn" type="primary" aria-label="远程连接" @click="downloadRdpLoginScript">
                        <CloudServerOutlined />
                        <span>远程</span>
                      </a-button>
                      <a-button
                        class="dashboard-page__topbar-action-btn dashboard-page__topbar-action-btn--power"
                        :disabled="isAnyPowerActionLoading"
                        @click="handleRebootAction"
                      >
                        <LoadingOutlined v-if="isActionLoading('power:reboot')" />
                        <AlertOutlined v-else />
                        <span>重启</span>
                      </a-button>
                      <a-button class="dashboard-page__topbar-action-btn dashboard-page__topbar-action-btn--share" @click="handleTopbarShare">
                        <ShareAltOutlined />
                        <span>一键分享</span>
                      </a-button>
                      <a-button
                        class="dashboard-page__topbar-refresh-btn"
                        aria-label="刷新实例"
                        :disabled="isActionLoading('refresh:summary')"
                        @click="refreshSummary"
                      >
                        <LoadingOutlined v-if="isActionLoading('refresh:summary')" />
                        <ReloadOutlined v-else />
                      </a-button>
                      <a-dropdown
                        v-model:open="moreMenuOpen"
                        :trigger="['click']"
                        placement="bottomRight"
                        overlay-class-name="dashboard-page__more-dropdown"
                        :get-popup-container="getMorePopupContainer"
                      >
                        <a-button class="dashboard-page__more-btn">
                          <EllipsisOutlined />
                          <span>更多操作</span>
                          <DownOutlined class="dashboard-page__more-chevron" />
                        </a-button>
                        <template #overlay>
                          <a-menu @click="handleTopbarMoreClick">
                            <a-menu-item
                              v-for="item in topbarMoreEntries"
                              :key="item.key"
                              :class="{
                                'dashboard-page__more-overflow-item': item.topbarOverflow,
                                [`dashboard-page__more-overflow-item--${item.key}`]: item.topbarOverflow,
                              }"
                            >
                              <component :is="resolvePortableIcon(item.icon)" />
                              <span>{{ item.label }}</span>
                            </a-menu-item>
                          </a-menu>
                        </template>
                      </a-dropdown>
                    </div>
                  </div>
                </section>

                <section class="dashboard-page__hero">
                  <div class="dashboard-page__main">
                    <a-card class="home-card dashboard-page__overview" :bordered="false" :body-style="{ padding: '0' }">
                      <div class="dashboard-page__overview-head dashboard-page__overview-head--server">
                        <div class="dashboard-page__overview-status">
                          <div class="dashboard-page__card-title">实例状态</div>
                          <span class="dashboard-page__server-dot" :class="`is-${hostStatusTone}`"></span>
                          <span class="dashboard-page__overview-status-text">{{ store.host.status || '未知' }}</span>
                          <a-button
                            type="link"
                            class="dashboard-page__overview-status-link"
                            @click="openPowerModal"
                          >
                            更改
                          </a-button>
                        </div>
                        <a-tooltip title="刷新">
                          <LoadingOutlined
                            v-if="isActionLoading('refresh:summary')"
                            class="dashboard-page__server-refresh"
                          />
                          <ReloadOutlined v-else class="dashboard-page__server-refresh" @click="refreshSummary" />
                        </a-tooltip>
                      </div>

                      <div class="dashboard-page__overview-main">
                        <div class="dashboard-page__server-copy">
                          <div class="dashboard-page__server-row">
                            <span class="dashboard-page__server-key">
                              <EnvironmentOutlined class="dashboard-page__server-key-icon" />
                              <span>区域线路</span>
                            </span>
                            <a-popover trigger="click" placement="topLeft">
                              <template #content>
                                <span class="dashboard-page__text-popover">{{ store.host.areaName || '-' }}｜{{ store.host.lineName || '-' }}</span>
                              </template>
                              <strong class="dashboard-page__server-pop-text">{{ store.host.areaName || '-' }}｜{{ store.host.lineName || '-' }}</strong>
                            </a-popover>
                          </div>
                          <div class="dashboard-page__server-row dashboard-page__server-row--os">
                            <span class="dashboard-page__server-key">
                              <DesktopOutlined class="dashboard-page__server-key-icon" />
                              <span>操作系统</span>
                            </span>
                            <div class="dashboard-page__server-value">
                              <a-popover trigger="click" placement="topLeft">
                                <template #content>
                                  <span class="dashboard-page__text-popover">{{ store.host.osName || '-' }}</span>
                                </template>
                                <strong class="dashboard-page__server-pop-text">{{ store.host.osName || '-' }}</strong>
                              </a-popover>
                              <a-button type="link" class="dashboard-page__inline-link" @click="openReinstallModal">
                                重装
                              </a-button>
                            </div>
                          </div>
                          <div class="dashboard-page__server-row dashboard-page__server-row--stack">
                            <span class="dashboard-page__server-key">
                              <ThunderboltOutlined class="dashboard-page__server-key-icon" />
                              <span>配置</span>
                            </span>
                            <div class="dashboard-page__server-tags">
                              <a-tag
                                v-for="item in statusSpecs"
                                :key="item"
                                class="dashboard-page__server-spec-tag"
                              >
                                {{ item }}
                              </a-tag>
                            </div>
                          </div>
                          <div class="dashboard-page__server-row dashboard-page__server-row--password">
                            <span class="dashboard-page__server-key">
                              <KeyOutlined class="dashboard-page__server-key-icon" />
                              <span>系统密码</span>
                            </span>
                            <div class="dashboard-page__server-value dashboard-page__server-value--password">
                              <strong
                                class="dashboard-page__mono"
                                :class="{ 'is-password-visible': showSystemPassword }"
                              >
                                {{ showSystemPassword ? (store.host.systemPassword || '-') : maskedSystemPassword }}
                              </strong>
                              <div class="dashboard-page__server-actions">
                                <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="showSystemPassword = !showSystemPassword">
                                  <component :is="showSystemPassword ? EyeInvisibleOutlined : EyeOutlined" />
                                </a-button>
                                <a-tooltip title="修改系统密码">
                                  <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="openPasswordModal('system')">
                                    <EditOutlined />
                                  </a-button>
                                </a-tooltip>
                                <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="copyValue(store.host.systemPassword, '系统密码')">
                                  <CopyOutlined />
                                </a-button>
                              </div>
                            </div>
                          </div>
                          <div class="dashboard-page__server-row">
                            <span class="dashboard-page__server-key">
                              <SafetyCertificateOutlined class="dashboard-page__server-key-icon" />
                              <span>到期时间</span>
                            </span>
                            <div class="dashboard-page__server-value dashboard-page__server-value--expire">
                              <a-popover trigger="click" placement="topLeft">
                                <template #content>
                                  <span class="dashboard-page__text-popover">
                                    {{ store.host.expireDate || '-' }}
                                    <template v-if="expireDaysText"> {{ expireDaysText }}</template>
                                  </span>
                                </template>
                                <strong class="dashboard-page__server-pop-text">{{ store.host.expireDate || '-' }}</strong>
                              </a-popover>
                              <small v-if="expireDaysText" class="dashboard-page__server-pop-text">{{ expireDaysText }}</small>
                            </div>
                          </div>
                        </div>

                        <div class="dashboard-page__visual">
                          <div
                            class="dashboard-page__visual-screen"
                            :class="{
                              'is-dark-art': !store.host.image && isDarkTheme,
                              'is-light-art': !store.host.image && !isDarkTheme,
                            }"
                          >
                            <img
                              v-if="store.host.image"
                              :src="store.host.image"
                              :alt="store.host.name || '实例截图'"
                              draggable="false"
                              @dragstart.prevent
                            />
                            <template v-else>
                              <img
                                class="dashboard-page__server-art"
                                src="/images/home-server-cloud.png"
                                alt=""
                                draggable="false"
                                @dragstart.prevent
                              />
                            </template>
                          </div>
                        </div>
                      </div>
                    </a-card>

                    <a-card class="home-card dashboard-page__remote-card" :bordered="false" :body-style="{ padding: '0' }">
                      <div class="dashboard-page__card-head">
                        <div class="dashboard-page__card-title">远程登录</div>
                        <a-button type="link" class="dashboard-page__remote-more" @click="handlePageSelect('vnc')">
                          查看更多方式 &gt;
                        </a-button>
                      </div>

                      <div
                        class="dashboard-page__remote-grid"
                        :class="`is-count-${Math.min(remotePrimaryMethods.length || 1, 3)}`"
                      >
                        <article
                          v-for="item in remotePrimaryMethods"
                          :key="item.key"
                          class="dashboard-page__remote-item"
                          :class="`is-${item.key}`"
                        >
                          <div class="dashboard-page__remote-item-head">
                            <div class="dashboard-page__remote-item-title" :class="`is-${item.key}`">
                              <img :src="resolveRemoteIcon(item.key)" alt="" aria-hidden="true" />
                              <span>{{ item.name }}</span>
                            </div>
                            <span class="dashboard-page__remote-item-state" :class="{ 'is-primary': item.primary }">
                              {{ item.primary ? '推荐' : '可用' }}
                            </span>
                          </div>
                          <div class="dashboard-page__remote-item-desc">{{ item.description }}</div>
                          <a-button
                            class="dashboard-page__remote-item-btn"
                            :type="item.primary ? 'primary' : 'default'"
                            @click="handleRemotePrimaryAction(item)"
                          >
                            {{ item.actionLabel || '登录' }}
                          </a-button>
                        </article>
                      </div>
                    </a-card>
                  </div>

                  <div class="dashboard-page__side">
                    <a-card
                      class="home-card dashboard-page__status-card"
                      :class="{ 'is-updating': statusCardUpdating }"
                      :bordered="false"
                      :body-style="{ padding: '0' }"
                    >
                      <div class="dashboard-page__card-head">
                        <div class="dashboard-page__card-title">实例状态</div>
                        <div class="dashboard-page__card-head-side">
                          <span class="dashboard-page__card-subtitle">实时更新</span>
                        <a-tooltip title="刷新">
                            <LoadingOutlined
                              v-if="isActionLoading('refresh:monitor')"
                              class="dashboard-page__server-refresh"
                            />
                            <ReloadOutlined v-else class="dashboard-page__server-refresh" @click="refreshStatusMonitor" />
                        </a-tooltip>
                        </div>
                      </div>
                      <div class="dashboard-page__status-list">
                        <div
                          v-for="item in statusItems"
                          :key="item.key"
                          class="dashboard-page__status-item"
                          :class="{ 'is-circle': item.chartType === 'circle' }"
                        >
                          <template v-if="item.chartType === 'circle'">
                            <div class="dashboard-page__status-circle">
                              <svg class="dashboard-page__status-circle-svg" viewBox="0 0 100 100" aria-hidden="true">
                                <circle
                                  cx="50"
                                  cy="50"
                                  r="36"
                                  fill="none"
                                  :stroke="item.trailColor"
                                  stroke-width="7"
                                />
                                <path
                                  class="dashboard-page__status-circle-arc"
                                  d="M 50 14 A 36 36 0 1 1 49.99 14"
                                  fill="none"
                                  :stroke="item.strokeColor"
                                  stroke-width="9"
                                  stroke-linecap="round"
                                  :style="{ strokeDashoffset: item.strokeDashoffset }"
                                />
                              </svg>
                              <div class="dashboard-page__status-circle-value">{{ item.centerText || item.value }}</div>
                            </div>
                            <div class="dashboard-page__status-circle-meta">
                              <div class="dashboard-page__status-circle-head">
                                <strong class="dashboard-page__status-circle-title">{{ item.label }}</strong>
                                <span v-if="item.unit" class="dashboard-page__status-circle-unit">{{ item.unit }}</span>
                              </div>
                              <div class="dashboard-page__status-mini-wave" aria-hidden="true">
                                <span
                                  v-for="(point, index) in item.trendPoints"
                                  :key="index"
                                  :style="{ '--bar-scale': point.ratio, background: item.strokeColor }"
                                ></span>
                              </div>
                              <small>{{ item.hint }}</small>
                            </div>
                          </template>
                          <template v-else>
                            <div class="dashboard-page__status-line">
                              <span>{{ item.label }}</span>
                              <strong>{{ item.value }}</strong>
                            </div>
                            <a-progress :percent="item.percent" :show-info="false" :stroke-width="10" :stroke-color="item.strokeColor" />
                            <small>{{ item.hint }}</small>
                          </template>
                        </div>
                      </div>
                    </a-card>

                    <a-card class="home-card dashboard-page__account-card" :bordered="false" :body-style="{ padding: '0' }">
                      <div class="dashboard-page__card-head">
                        <div class="dashboard-page__card-title">账号信息</div>
                        <a-button
                          type="link"
                          class="dashboard-page__card-subtitle-link"
                          @click="handlePageSelect('vnc')"
                        >
                          登录凭据 &gt;
                        </a-button>
                      </div>
                      <div class="dashboard-page__account-list">
                        <div class="dashboard-page__account-row">
                          <span class="dashboard-page__account-key">
                            <DesktopOutlined class="dashboard-page__account-key-icon" />
                            <span>系统类型</span>
                          </span>
                          <div class="dashboard-page__account-main">
                            <strong class="dashboard-page__account-text">{{ osFamilyLabel }}</strong>
                          </div>
                          <div class="dashboard-page__account-actions">
                            <a-button class="dashboard-page__account-os-btn" type="text" size="small" disabled>
                              <component :is="osIcon" />
                            </a-button>
                          </div>
                        </div>
                        <div class="dashboard-page__account-row">
                          <span class="dashboard-page__account-key">
                            <CloudServerOutlined class="dashboard-page__account-key-icon" />
                            <span>远程地址</span>
                          </span>
                          <div class="dashboard-page__account-main">
                            <a-popover trigger="click" placement="topLeft">
                              <template #content>
                                <span class="dashboard-page__text-popover">{{ store.host.remoteAddress || '-' }}</span>
                              </template>
                              <strong class="dashboard-page__account-text dashboard-page__mono dashboard-page__account-pop-text">
                                {{ store.host.remoteAddress || '-' }}
                              </strong>
                            </a-popover>
                          </div>
                          <div class="dashboard-page__account-actions">
                            <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="copyValue(store.host.remoteAddress, '远程地址')">
                              <CopyOutlined />
                            </a-button>
                          </div>
                        </div>
                        <div class="dashboard-page__account-row">
                          <span class="dashboard-page__account-key">
                            <SafetyOutlined class="dashboard-page__account-key-icon" />
                            <span>系统用户</span>
                          </span>
                          <div class="dashboard-page__account-main">
                            <strong class="dashboard-page__account-text dashboard-page__mono">{{ systemUser }}</strong>
                          </div>
                          <div class="dashboard-page__account-actions">
                            <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="copyValue(systemUser, '系统用户')">
                              <CopyOutlined />
                            </a-button>
                          </div>
                        </div>
                        <div class="dashboard-page__account-row">
                          <span class="dashboard-page__account-key">
                            <KeyOutlined class="dashboard-page__account-key-icon" />
                            <span>系统密码</span>
                          </span>
                          <div class="dashboard-page__account-main">
                            <strong class="dashboard-page__account-text dashboard-page__mono">{{ showSystemPassword ? (store.host.systemPassword || '-') : maskedSystemPassword }}</strong>
                          </div>
                          <div class="dashboard-page__account-actions">
                            <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="showSystemPassword = !showSystemPassword">
                              <component :is="showSystemPassword ? EyeInvisibleOutlined : EyeOutlined" />
                            </a-button>
                            <a-tooltip title="修改系统密码">
                              <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="openPasswordModal('system')">
                                <EditOutlined />
                              </a-button>
                            </a-tooltip>
                            <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="copyValue(store.host.systemPassword, '系统密码')">
                              <CopyOutlined />
                            </a-button>
                          </div>
                        </div>
                        <div class="dashboard-page__account-row">
                          <span class="dashboard-page__account-key">
                            <ToolOutlined class="dashboard-page__account-key-icon" />
                            <span>面板密码</span>
                          </span>
                          <div class="dashboard-page__account-main">
                            <strong class="dashboard-page__account-text dashboard-page__mono">{{ showPanelPassword ? (store.host.panelPassword || '-') : maskedPanelPassword }}</strong>
                          </div>
                          <div class="dashboard-page__account-actions">
                            <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="showPanelPassword = !showPanelPassword">
                              <component :is="showPanelPassword ? EyeInvisibleOutlined : EyeOutlined" />
                            </a-button>
                            <a-tooltip title="修改面板密码">
                              <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="openPasswordModal('panel')">
                                <EditOutlined />
                              </a-button>
                            </a-tooltip>
                            <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="copyValue(store.host.panelPassword, '面板密码')">
                              <CopyOutlined />
                            </a-button>
                          </div>
                        </div>
                      </div>
                    </a-card>
                  </div>
                </section>

                <section class="dashboard-page__lower-grid">
                  <a-card class="home-card dashboard-page__network-card" :bordered="false" :body-style="{ padding: '0' }">
                    <div class="dashboard-page__card-head">
                      <div class="dashboard-page__card-title">网络信息</div>
                      <a-button type="link" class="dashboard-page__remote-more" @click="handlePageSelect('network-detail')">
                        查看详情 &gt;
                      </a-button>
                    </div>
                    <div class="dashboard-page__network-content">
                      <div class="dashboard-page__network-item dashboard-page__network-primary">
                        <span>公网 IP</span>
                        <div class="dashboard-page__network-ip-row">
                          <strong class="dashboard-page__mono">{{ homeNetworkSummary.publicIp }}</strong>
                          <a-button class="dashboard-page__copy-btn" type="text" size="small" @click="copyValue(homeNetworkSummary.publicIp, '公网 IP')">
                            <CopyOutlined />
                          </a-button>
                        </div>
                        <small>{{ homeNetworkSummary.region }} · {{ homeNetworkSummary.line }}</small>
                      </div>

                      <div class="dashboard-page__network-item dashboard-page__network-metric dashboard-page__network-metric--bandwidth">
                        <span>带宽</span>
                        <strong>{{ homeNetworkSummary.bandwidth }}</strong>
                        <small>{{ homeNetworkSummary.peakBandwidth }}</small>
                      </div>
                      <div class="dashboard-page__network-item dashboard-page__network-metric dashboard-page__network-metric--traffic">
                        <span>流量使用率</span>
                        <strong>{{ homeNetworkSummary.trafficPercentText }}</strong>
                        <small>已用 {{ homeNetworkSummary.trafficUsed }} / 上限 {{ homeNetworkSummary.trafficLimit }}</small>
                        <div class="dashboard-page__network-bar" aria-hidden="true">
                          <i :style="{ width: homeNetworkSummary.trafficPercentWidth }"></i>
                        </div>
                      </div>
                    </div>
                  </a-card>

                  <a-card class="home-card dashboard-page__feature-card" :bordered="false" :body-style="{ padding: '0' }">
                    <a-button
                      class="dashboard-page__feature-arrow dashboard-page__feature-arrow--prev"
                      type="text"
                      aria-label="上一个功能"
                      @click.stop="showPreviousFeature"
                    >
                      <LeftOutlined />
                    </a-button>
                    <div
                      v-if="activeQuickCard"
                      class="dashboard-page__feature-slide"
                      @click="handlePageSelect(activeQuickCard.targetKey)"
                    >
                      <div class="dashboard-page__quick-copy">
                        <div class="dashboard-page__quick-head">
                          <div class="dashboard-page__quick-title">{{ activeQuickCard.title }}</div>
                        </div>
                        <div class="dashboard-page__quick-subtitle">{{ activeQuickCard.summaryText }}</div>
                        <div class="dashboard-page__quick-note">{{ activeQuickCard.detailText }}</div>
                      </div>
                      <div class="dashboard-page__quick-media">
                        <RightOutlined class="dashboard-page__quick-arrow" />
                        <div class="dashboard-page__quick-graphic" :class="`is-${activeQuickCard.key}`">
                          <img
                            v-if="activeQuickCard.graphicSrc"
                            class="dashboard-page__quick-asset"
                            :src="activeQuickCard.graphicSrc"
                            alt=""
                            aria-hidden="true"
                          />
                          <component v-else :is="resolveQuickIcon(activeQuickCard.icon)" class="dashboard-page__quick-icon" />
                        </div>
                      </div>
                    </div>
                    <a-button
                      class="dashboard-page__feature-arrow dashboard-page__feature-arrow--next"
                      type="text"
                      aria-label="下一个功能"
                      @click.stop="showNextFeature"
                    >
                      <RightOutlined />
                    </a-button>
                    <div class="dashboard-page__feature-dots">
                      <button
                        v-for="(link, index) in quickCards"
                        :key="link.key"
                        type="button"
                        :aria-label="`切换到${link.title}`"
                        :class="{ 'is-active': index === activeFeatureIndex }"
                        @click="setActiveFeature(index)"
                      ></button>
                    </div>
                  </a-card>
                </section>

              </div>
              <MmuiSubpageView
                v-else
                :key="activePageKey"
                :page-key="activePageKey"
                :pages="store.pages"
                :boot-modal-request="bootModalRequest"
                @boot-modal-request-consumed="bootModalRequest = 0"
                @reinstall-modal-request="openReinstallModal"
              />
            </Transition>
          </div>
        </a-layout-content>
      </a-layout>

      <a-drawer
        v-model:open="mobileSidebarOpen"
        placement="left"
        :closable="false"
        :width="256"
        class="mmui-mobile-drawer"
        root-class-name="mmui-mobile-drawer-root"
      >
        <MmuiSidebar :active-key="activePageKey" :collapsed="false" @select="handlePageSelect" @toggle-sidebar="handleMenuToggle" />
      </a-drawer>

      <a-modal
        v-model:open="powerModalOpen"
        title="电源管理"
        :footer="null"
        :width="520"
        centered
        class="dashboard-page__action-modal"
      >
        <div class="dashboard-page__power-modal">
          <div class="dashboard-page__modal-summary">
            <span class="dashboard-page__modal-summary-icon" aria-hidden="true">
              <ThunderboltOutlined />
            </span>
            <div class="dashboard-page__modal-summary-main">
              <span>当前实例</span>
              <strong>{{ store.host.name || 'ECS' }}</strong>
            </div>
            <a-tag :color="hostStatusBadge">{{ store.host.status || '未知' }}</a-tag>
          </div>

          <div class="dashboard-page__power-actions">
            <button
              type="button"
              class="dashboard-page__power-action"
              :class="{ 'is-active': hostStatusTone !== 'success' || isActionLoading('power:boot'), 'is-loading': isActionLoading('power:boot') }"
              :disabled="isAnyPowerActionLoading"
              @click="submitPowerAction('boot')"
            >
              <span class="dashboard-page__power-action-icon">
                <LoadingOutlined v-if="isActionLoading('power:boot')" />
                <ThunderboltOutlined v-else />
              </span>
              <span>
                <strong>启动实例</strong>
                <small>将实例切换为运行中状态</small>
              </span>
            </button>
            <button
              type="button"
              class="dashboard-page__power-action is-danger"
              :class="{ 'is-active': hostStatusTone === 'success' || isActionLoading('power:shutdown'), 'is-loading': isActionLoading('power:shutdown') }"
              :disabled="isAnyPowerActionLoading"
              @click="submitPowerAction('shutdown')"
            >
              <span class="dashboard-page__power-action-icon">
                <LoadingOutlined v-if="isActionLoading('power:shutdown')" />
                <PoweroffOutlined v-else />
              </span>
              <span>
                <strong>关闭实例</strong>
                <small>会中断当前业务连接</small>
              </span>
            </button>
          </div>
        </div>
      </a-modal>

      <a-modal
        v-model:open="reinstallModalOpen"
        title="重装系统"
        :footer="null"
        :width="720"
        centered
        class="dashboard-page__action-modal dashboard-page__reinstall-modal"
      >
        <div class="dashboard-page__reinstall-body">
          <div class="dashboard-page__reinstall-head">
            <div>
              <span>当前系统</span>
              <strong>{{ store.host.osName || '-' }}</strong>
            </div>
            <a-tag class="dashboard-page__reinstall-quota">{{ reinstallQuotaText }}</a-tag>
          </div>

          <div v-if="reinstallStep === 0" class="dashboard-page__reinstall-picker">
            <div class="dashboard-page__reinstall-family-list" aria-label="系统类型">
              <button
                v-for="family in reinstallFamilies"
                :key="family.title"
                type="button"
                class="dashboard-page__reinstall-family"
                :class="{ 'is-active': family.title === selectedReinstallFamilyTitle }"
                @click="selectReinstallFamily(family.title)"
              >
                <span
                  class="dashboard-page__reinstall-glyph"
                  :class="`is-${resolveReinstallFamilyType(family)}`"
                  aria-hidden="true"
                >
                  <component :is="resolveReinstallFamilyIcon(family)" />
                </span>
                <span>
                  <strong>{{ family.title }}</strong>
                  <small>{{ family.subtitle || `${family.options?.length || 0} 个镜像` }}</small>
                </span>
              </button>
            </div>

            <div class="dashboard-page__reinstall-image-panel">
              <div class="dashboard-page__reinstall-panel-head">
                <span>具体系统</span>
                <strong>{{ activeReinstallFamily?.title || '-' }}</strong>
              </div>
              <div class="dashboard-page__reinstall-option-list">
                <button
                  v-for="option in activeReinstallOptions"
                  :key="option"
                  type="button"
                  class="dashboard-page__reinstall-option"
                  :class="{ 'is-active': option === selectedReinstallOption }"
                  @click="selectedReinstallOption = option"
                >
                  <span>
                    <strong>{{ option }}</strong>
                    <small>{{ activeReinstallFamily?.description || '选择后设置登录密码' }}</small>
                  </span>
                  <CheckCircleFilled v-if="option === selectedReinstallOption" />
                </button>
              </div>
            </div>
          </div>

          <div v-else class="dashboard-page__reinstall-confirm">
            <div class="dashboard-page__confirm-row">
              <span>目标系统</span>
              <strong>{{ selectedReinstallOption || '-' }}</strong>
            </div>
            <div class="dashboard-page__confirm-row">
              <span>新密码</span>
              <a-input-password v-model:value="reinstallPassword" placeholder="请输入新的系统密码" />
            </div>
          </div>

          <div class="dashboard-page__modal-actions">
            <a-button @click="closeReinstallModal">取消</a-button>
            <a-button v-if="reinstallStep === 1" @click="reinstallStep = 0">上一步</a-button>
            <a-button v-if="reinstallStep === 0" type="primary" @click="goReinstallNextStep">下一步</a-button>
            <a-button
              v-else
              type="primary"
              danger
              :loading="isActionLoading('system:reinstall')"
              :disabled="isActionLoading('system:reinstall')"
              @click="submitReinstall"
            >
              确认重装
            </a-button>
          </div>
        </div>
      </a-modal>

      <a-modal
        v-model:open="passwordModalOpen"
        :title="passwordModalTitle"
        :footer="null"
        :width="520"
        centered
        class="dashboard-page__action-modal"
      >
        <div class="dashboard-page__password-modal">
          <div class="dashboard-page__modal-summary">
            <span class="dashboard-page__modal-summary-icon" aria-hidden="true">
              <KeyOutlined />
            </span>
            <div class="dashboard-page__modal-summary-main">
              <span>{{ passwordMode === 'panel' ? '控制台登录凭据' : '系统登录凭据' }}</span>
              <strong>{{ passwordMode === 'panel' ? '更新面板密码' : '更新系统密码' }}</strong>
            </div>
          </div>

          <div class="dashboard-page__confirm-row">
            <span>{{ passwordMode === 'panel' ? '面板密码' : '系统密码' }}</span>
            <a-input-password
              v-model:value="passwordDraft"
              :maxlength="passwordMaxLength"
              :placeholder="passwordPlaceholder"
              @pressEnter="submitPasswordUpdate"
            />
          </div>

          <div class="dashboard-page__confirm-help">
            {{ passwordRuleText }}
          </div>

          <div class="dashboard-page__modal-actions">
            <a-button @click="passwordModalOpen = false">取消</a-button>
            <a-button
              type="primary"
              :loading="isActionLoading(`password:${passwordMode}`)"
              :disabled="isActionLoading(`password:${passwordMode}`)"
              @click="submitPasswordUpdate"
            >
              保存
            </a-button>
          </div>
        </div>
      </a-modal>
      <MmuiTutorial
        ref="tutorialRef"
        :auto="false"
        @navigate="handleTutorialNavigate"
      />
    </a-layout>
  </a-spin>
</template>

<script setup>
import { message } from 'ant-design-vue';
import {
  ApiOutlined,
  AlertOutlined,
  ArrowRightOutlined,
  CheckCircleFilled,
  CloudServerOutlined,
  CopyOutlined,
  DesktopOutlined,
  DownOutlined,
  EditOutlined,
  EllipsisOutlined,
  EnvironmentOutlined,
  EyeInvisibleOutlined,
  EyeOutlined,
  KeyOutlined,
  LeftOutlined,
  LoadingOutlined,
  PoweroffOutlined,
  ReloadOutlined,
  RightOutlined,
  SafetyOutlined,
  SafetyCertificateOutlined,
  ShareAltOutlined,
  SettingOutlined,
  ToolOutlined,
  ThunderboltOutlined,
  WifiOutlined,
} from '@ant-design/icons-vue';
import { computed, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import remoteConsoleGraphic from '@/assets/iconly-glass/console.svg';
import remoteFileGraphic from '@/assets/iconly-glass/file.svg';
import remoteNetworkGraphic from '@/assets/iconly-glass/network.svg';
import quickBackupGraphic from '@/assets/iconly-glass/Paper Plus.svg';
import quickPortGraphic from '@/assets/iconly-glass/Menu.svg';
import quickSnapshotGraphic from '@/assets/iconly-glass/Camera.svg';
import quickStrategyGraphic from '@/assets/iconly-glass/Shield.svg';
import almalinuxIconSvg from 'simple-icons/icons/almalinux.svg?raw';
import archlinuxIconSvg from 'simple-icons/icons/archlinux.svg?raw';
import centosIconSvg from 'simple-icons/icons/centos.svg?raw';
import debianIconSvg from 'simple-icons/icons/debian.svg?raw';
import fedoraIconSvg from 'simple-icons/icons/fedora.svg?raw';
import linuxIconSvg from 'simple-icons/icons/linux.svg?raw';
import rockylinuxIconSvg from 'simple-icons/icons/rockylinux.svg?raw';
import ubuntuIconSvg from 'simple-icons/icons/ubuntu.svg?raw';
import { useThemeMode } from '@/composables/useThemeMode';
import { useActionLocks } from '@/composables/useActionLocks';
import MmuiHeader from '@/components/layout/MmuiHeader.vue';
import MmuiSidebar from '@/components/layout/MmuiSidebar.vue';
import MmuiSubpageView from '@/components/pages/MmuiSubpageView.vue';
import MmuiTutorial from '@/components/tutorial/MmuiTutorial.vue';
import { mmuiPageMeta } from '@/config/navigation';
import { useDashboardStore } from '@/stores/dashboard';
import {
  getRemoteActionLabel,
  getRemoteUser,
  isWindowsHost as checkWindowsHost,
  normalizeRemoteLoginMethod,
  triggerHostRemoteAccess,
} from '@/utils/remoteAccess';

const store = useDashboardStore();
const themeMode = useThemeMode();
const { isActionLoading, runWithActionLoading } = useActionLocks();
function extractSimpleIcon(svgRaw) {
  return {
    title: svgRaw.match(/<title>(.*?)<\/title>/)?.[1] || 'Linux',
    path: svgRaw.match(/<path d="([^"]+)"/)?.[1] || '',
  };
}

function createSimpleIconComponent(svgRaw) {
  const icon = extractSimpleIcon(svgRaw);
  return (_props, { attrs }) => h(
    'svg',
    {
      ...attrs,
      viewBox: '0 0 24 24',
      width: '1em',
      height: '1em',
      fill: 'currentColor',
      role: 'img',
      'aria-label': icon.title,
    },
    [
      h('title', icon.title),
      h('path', { d: icon.path }),
    ],
  );
}

const UbuntuIcon = createSimpleIconComponent(ubuntuIconSvg);
const DebianIcon = createSimpleIconComponent(debianIconSvg);
const CentosIcon = createSimpleIconComponent(centosIconSvg);
const LinuxIcon = createSimpleIconComponent(linuxIconSvg);
const FedoraIcon = createSimpleIconComponent(fedoraIconSvg);
const ArchlinuxIcon = createSimpleIconComponent(archlinuxIconSvg);
const RockylinuxIcon = createSimpleIconComponent(rockylinuxIconSvg);
const AlmalinuxIcon = createSimpleIconComponent(almalinuxIconSvg);
const FlatWindowsIcon = (_props, { attrs }) => h(
  'svg',
  {
    ...attrs,
    viewBox: '0 0 24 24',
    width: '1em',
    height: '1em',
    fill: 'currentColor',
    role: 'img',
    'aria-label': 'Windows',
  },
  [
    h('rect', { x: 3, y: 4, width: 8, height: 7, rx: 0.9 }),
    h('rect', { x: 13, y: 4, width: 8, height: 7, rx: 0.9 }),
    h('rect', { x: 3, y: 13, width: 8, height: 7, rx: 0.9 }),
    h('rect', { x: 13, y: 13, width: 8, height: 7, rx: 0.9 }),
  ],
);

const quickIconMap = {
  BranchesOutlined: ApiOutlined,
  CameraOutlined: CopyOutlined,
  CopyOutlined,
  SafetyCertificateOutlined,
};

const portableIconMap = {
  BranchesOutlined: ApiOutlined,
  AlertOutlined,
  DesktopOutlined,
  ReloadOutlined,
  SettingOutlined,
  ToolOutlined,
  ArrowRightOutlined,
  ShareAltOutlined,
};
const remoteIconMap = {
  web: remoteNetworkGraphic,
  rdp: remoteFileGraphic,
  ssh: remoteNetworkGraphic,
  vnc: remoteConsoleGraphic,
};

const mobileSidebarOpen = ref(false);
const desktopSidebarOpen = ref(true);
const desktopSidebarCollapsed = ref(false);
const isDesktopViewport = ref(true);
const viewportWidth = ref(1440);
const isNarrowDashboardViewport = computed(() => viewportWidth.value <= 640);
const activePageKey = ref('panel');
const moreMenuOpen = ref(false);
const bootModalRequest = ref(0);
const powerModalOpen = ref(false);
const reinstallModalOpen = ref(false);
const reinstallStep = ref(0);
const selectedReinstallFamilyTitle = ref('');
const selectedReinstallOption = ref('');
const reinstallPassword = ref('');
const passwordModalOpen = ref(false);
const passwordMode = ref('system');
const passwordDraft = ref('');
const showSystemPassword = ref(false);
const showPanelPassword = ref(false);
const activeFeatureIndex = ref(0);
const statusCardUpdating = ref(false);
const tutorialRef = ref(null);
const animatedStatusProgress = ref({
  cpu: 0,
  memory: 0,
  network: 0,
});
let featureAutoTimer = 0;
let statePollTimer = 0;
let monitorPollTimer = 0;
let transitionPollTimer = 0;
let transitionPollToken = 0;
let statusUpdateTimer = 0;
let statusProgressFrame = 0;
let hasAnimatedStatusProgress = false;
let tutorialConsoleBound = false;
const STATUS_CIRCLE_LENGTH = 226.2;
const consoleTitle = computed(() => store.brand.consoleTitle || '云管理系统');
const consoleLogo = computed(() => store.brand.consoleLogo || '');
const pageTitle = computed(() => (
  activePageKey.value === 'panel'
    ? consoleTitle.value
    : (mmuiPageMeta[activePageKey.value]?.title || consoleTitle.value)
));
const isDarkTheme = computed(() => themeMode.isDark.value);

const latestCpu = computed(() => getLastMetric(store.monitors.cpu));
const latestMemory = computed(() => getLastMetric(store.monitors.memory));
const latestNetwork = computed(() => getLastMetric(store.monitors.network));
const upstreamBandwidthText = computed(() => formatBandwidthValue(store.host.bandwidth));
const downstreamBandwidthText = computed(() => formatBandwidthValue(store.host.bandwidthIn ?? store.host.bandwidth_in));
const networkBandwidthLimitKbps = computed(() => parseBandwidthLimitKbps(store.host.bandwidth));
const latestNetworkPercent = computed(() => {
  const limit = networkBandwidthLimitKbps.value;
  if (!limit) {
    return 0;
  }

  return Math.min(100, Math.round((latestNetwork.value / limit) * 100));
});
const systemUser = computed(() => getRemoteUser(store.host));
const osFamilyLabel = computed(() => (
  checkWindowsHost(store.host) ? 'Windows' : 'Linux'
));
const osIcon = computed(() => resolveSystemIcon(store.host.osName));

function resolveSystemFamilyType(value) {
  const text = String(value || '').toLowerCase();
  if (text.includes('windows') || /\bwin/.test(text)) return 'windows';
  if (text.includes('ubuntu')) return 'ubuntu';
  if (text.includes('debian')) return 'debian';
  if (text.includes('centos')) return 'centos';
  if (text.includes('fedora')) return 'fedora';
  if (text.includes('arch')) return 'arch';
  if (text.includes('rocky')) return 'rocky';
  if (text.includes('alma')) return 'alma';
  if (text.includes('linux')) return 'linux';
  return 'generic';
}

function resolveSystemIcon(value) {
  const familyType = resolveSystemFamilyType(value);
  if (familyType === 'windows') return FlatWindowsIcon;
  if (familyType === 'ubuntu') return UbuntuIcon;
  if (familyType === 'debian') return DebianIcon;
  if (familyType === 'centos') return CentosIcon;
  if (familyType === 'fedora') return FedoraIcon;
  if (familyType === 'arch') return ArchlinuxIcon;
  if (familyType === 'rocky') return RockylinuxIcon;
  if (familyType === 'alma') return AlmalinuxIcon;
  if (familyType === 'linux') return LinuxIcon;
  return DesktopOutlined;
}
function resolveHostStatusKind(host) {
  const stateCode = Number(host?.state);
  const powerState = String(host?.powerState || '').toLowerCase();
  const statusText = String(host?.status || '').toLowerCase();

  if (
    ['error', 'failed', 'failure', 'unknown_error'].includes(powerState)
    || statusText.includes('异常')
    || statusText.includes('失败')
    || statusText.includes('错误')
  ) {
    return 'error';
  }

  if (
    ['pending', 'starting', 'stopping', 'rebooting', 'reinstalling'].includes(powerState)
    || statusText.includes('开机')
    || statusText.includes('启动')
    || statusText.includes('关机中')
    || statusText.includes('重启')
    || statusText.includes('重装')
    || statusText.includes('处理中')
    || statusText.includes('等待')
  ) {
    return 'pending';
  }

  if (stateCode === 2 || powerState === 'running' || statusText.includes('运行')) {
    return 'running';
  }

  if (
    stateCode === 3
    || ['stopped', 'shutdown', 'poweroff', 'closed'].includes(powerState)
    || statusText.includes('关机')
    || statusText.includes('关闭')
    || statusText.includes('停止')
  ) {
    return 'stopped';
  }

  return 'unknown';
}

const hostStatusTone = computed(() => {
  const kind = resolveHostStatusKind(store.host);
  if (kind === 'running') {
    return 'success';
  }
  if (kind === 'stopped') {
    return 'danger';
  }
  if (kind === 'error') {
    return 'error';
  }
  if (kind === 'pending') {
    return 'warning';
  }
  return 'neutral';
});
const hostStatusBadge = computed(() => {
  if (hostStatusTone.value === 'success') return 'success';
  if (hostStatusTone.value === 'danger' || hostStatusTone.value === 'error') return 'error';
  if (hostStatusTone.value === 'warning') return 'warning';
  return 'default';
});
const isAnyPowerActionLoading = computed(() => (
  isActionLoading('power:boot')
  || isActionLoading('power:shutdown')
  || isActionLoading('power:reboot')
));
const remoteHost = computed(() => String(store.host.remoteAddress || '').split(':')[0] || '-');
const reinstallQuotaText = computed(() => store.pages?.reinstall?.quota || '0/0');
const reinstallFamilies = computed(() => (
  Array.isArray(store.pages?.reinstall?.cards) ? store.pages.reinstall.cards : []
));
const activeReinstallFamily = computed(() => (
  reinstallFamilies.value.find((family) => family.title === selectedReinstallFamilyTitle.value) || reinstallFamilies.value[0] || null
));
const activeReinstallOptions = computed(() => activeReinstallFamily.value?.options || []);
function clampRatio(value) {
  return Math.max(0, Math.min(1, Number(value) || 0));
}

function percentToRatio(percent) {
  return clampRatio((Number(percent) || 0) / 100);
}

function easeOutCubic(value) {
  return 1 - Math.pow(1 - clampRatio(value), 3);
}

function animateStatusProgress(targets, duration = 560) {
  if (typeof window === 'undefined') {
    animatedStatusProgress.value = targets;
    return;
  }

  window.cancelAnimationFrame(statusProgressFrame);
  const start = { ...animatedStatusProgress.value };
  const startedAt = window.performance.now();

  const tick = (now) => {
    const eased = easeOutCubic((now - startedAt) / duration);
    const next = {};

    Object.keys(targets).forEach((key) => {
      const from = Number(start[key]) || 0;
      const to = Number(targets[key]) || 0;
      next[key] = from + ((to - from) * eased);
    });

    animatedStatusProgress.value = next;

    if (eased < 1) {
      statusProgressFrame = window.requestAnimationFrame(tick);
      return;
    }

    animatedStatusProgress.value = targets;
    statusProgressFrame = 0;
  };

  statusProgressFrame = window.requestAnimationFrame(tick);
}

function buildTrendPoints(values, limit) {
  const size = 14;
  const samples = Array.isArray(values) ? values.slice(-size).map((value) => Math.max(0, Number(value) || 0)) : [];
  const normalized = [...Array.from({ length: Math.max(0, size - samples.length) }, () => 0), ...samples].slice(-size);
  const max = Number(limit) > 0 ? Number(limit) : Math.max(...normalized, 1);
  return normalized.map((value) => ({
    ratio: Math.max(0.18, Math.min(1, value / max)).toFixed(3),
  }));
}

function parseBandwidthLimitKbps(value) {
  if (value === null || value === undefined || value === '') {
    return 0;
  }

  if (typeof value === 'number') {
    return value > 0 ? value * 128 : 0;
  }

  const text = String(value).trim().toLowerCase();
  const match = text.match(/(\d+(?:\.\d+)?)/);
  if (!match) {
    return 0;
  }

  const numeric = Number(match[1]);
  if (!Number.isFinite(numeric) || numeric <= 0) {
    return 0;
  }

  if (text.includes('gbps') || text.includes('gbit')) {
    return numeric * 1024 * 128;
  }

  if (text.includes('kb/s') || text.includes('kbps') || text.includes('k/s')) {
    return numeric;
  }

  return numeric * 128;
}

function formatBandwidthValue(value) {
  const text = String(value ?? '').trim();
  if (!text) {
    return '';
  }

  if (/^\d+(\.\d+)?$/.test(text)) {
    const numeric = Number(text);
    return numeric > 0 ? `${numeric} Mbps` : '';
  }

  return text;
}

function buildBandwidthPeakText() {
  const upstream = upstreamBandwidthText.value;
  if (!upstream) {
    return '峰值未配置';
  }

  return downstreamBandwidthText.value
    ? `峰值 ${upstream} · 下行 ${downstreamBandwidthText.value}`
    : `峰值 ${upstream}`;
}

const statusItems = computed(() => ([
  {
    key: 'cpu',
    label: 'CPU 占用',
    value: `${latestCpu.value}%`,
    percent: latestCpu.value,
    size: 122,
    chartType: 'circle',
    hint: `规格 ${store.host.cpu || 0} 核`,
    strokeColor: 'var(--mmui-accent-blue)',
    trailColor: 'rgba(var(--mmui-accent-blue-rgb), 0.14)',
    trendPoints: buildTrendPoints(store.monitors.cpu),
  },
  {
    key: 'memory',
    label: '内存占用',
    value: `${latestMemory.value}%`,
    percent: latestMemory.value,
    size: 122,
    chartType: 'circle',
    hint: `规格 ${store.host.memory || 0} GB`,
    strokeColor: '#f0a11a',
    trailColor: 'rgba(240, 161, 26, 0.14)',
    trendPoints: buildTrendPoints(store.monitors.memory),
  },
  {
    key: 'network',
    label: '网络负载',
    value: `${latestNetwork.value} KBps`,
    centerText: `${latestNetwork.value}`,
    percent: latestNetworkPercent.value,
    size: 122,
    chartType: 'circle',
    unit: 'KBps',
    hint: upstreamBandwidthText.value ? `峰值 ${upstreamBandwidthText.value}` : '峰值未配置',
    strokeColor: '#0ca678',
    trailColor: 'rgba(12, 166, 120, 0.14)',
    trendPoints: buildTrendPoints(store.monitors.network, networkBandwidthLimitKbps.value),
  },
]).map((item) => ({
  ...item,
  progressRatio: percentToRatio(item.percent).toFixed(3),
  strokeDashoffset: (
    STATUS_CIRCLE_LENGTH
    * (1 - clampRatio(animatedStatusProgress.value[item.key]))
  ).toFixed(3),
})));
const statusSpecs = computed(() => ([
  `${store.host.cpu || 0}核`,
  `${store.host.memory || 0}G内存`,
  `${store.host.disk || 0}G存储`,
  `${store.host.bandwidth || 0}Mbps`,
]));

const quickRouteMap = {
  port: 'port',
  snapshot: 'snapshot',
  backup: 'backup',
  firewall: 'strategy',
};
const quickGraphicMap = {
  port: quickPortGraphic,
  snapshot: quickSnapshotGraphic,
  backup: quickBackupGraphic,
  firewall: quickStrategyGraphic,
};
const quickDetailMap = {
  port: () => `已使用 ${store.quotas?.portMapping?.used ?? 0} 个端口映射`,
  snapshot: () => `已创建 ${store.quotas?.snapshot?.used ?? 0} 个快照`,
  backup: () => `已创建 ${store.quotas?.backup?.used ?? 0} 份备份`,
  firewall: () => `已启用 ${store.quotas?.firewall?.used ?? 0} 项策略`,
};
const quickCards = computed(() => (
  (store.quickLinks || []).map((item) => ({
    ...item,
    targetKey: quickRouteMap[item.key] || 'panel',
    summaryText: String(item.subtitle || '').replace(/^数目：/, '数量：'),
    detailText: quickDetailMap[item.key]?.() || String(item.subtitle || ''),
    graphicSrc: quickGraphicMap[item.key] || '',
  }))
));
const activeQuickCard = computed(() => {
  const cards = quickCards.value;
  if (!cards.length) {
    return null;
  }

  return cards[activeFeatureIndex.value % cards.length];
});
const homeNetworkSummary = computed(() => {
  const publicIp = findNetworkValue(['外部 IP', '公网 IP', 'IP']);
  const region = findNetworkValue(['区域', '地区']) || store.host.areaName;
  const line = findNetworkValue(['线路']) || store.host.lineName;
  const bandwidth = upstreamBandwidthText.value || findNetworkValue(['带宽']);
  const upstream = findNetworkRelatedValue(['上行流量']);
  const downstream = findNetworkRelatedValue(['下行流量']);
  const trafficLimit = findNetworkValue(['流量上限', '月流量上限', '月上限', '总流量上限', '流量限制', '月流量限制', '总流量限制']) || readTrafficLimit();
  const hasTrafficUsageData = hasNetworkValue(upstream) || hasNetworkValue(downstream);
  const trafficUsedMb = (parseDataSize(upstream) || 0) + (parseDataSize(downstream) || 0);
  const trafficLimitMb = parseTrafficLimitSize(trafficLimit);
  const trafficPercent = hasTrafficUsageData && trafficLimitMb > 0 ? Math.round((trafficUsedMb / trafficLimitMb) * 100) : null;
  const hasTrafficLimit = trafficLimitMb > 0;

  return {
    publicIp: displayNetworkValue(publicIp),
    region: displayNetworkValue(region),
    line: displayNetworkValue(line),
    bandwidth: displayNetworkValue(bandwidth),
    peakBandwidth: buildBandwidthPeakText(),
    trafficUsed: hasTrafficUsageData ? formatDataSize(trafficUsedMb) : '未知',
    trafficLimit: displayTrafficLimitValue(trafficLimit),
    trafficPercentText: trafficPercent === null ? (hasTrafficUsageData ? (hasTrafficLimit ? '未配置' : '无限制') : '未知') : `${trafficPercent}%`,
    trafficPercentWidth: trafficPercent === null ? '0%' : `${Math.min(trafficPercent, 100)}%`,
  };
});
const portableRouteMap = {
  rescue: 'system-control',
};
const portableEntries = computed(() => store.portableActions || []);
const filteredPortableEntries = computed(() => portableEntries.value.filter((entry) => {
  const key = String(entry?.key || '').toLowerCase();
  const label = String(entry?.label || '');
  return key !== 'share'
    && key !== 'download'
    && key !== 'remote'
    && !label.includes('一键远程');
}));
const topbarMoreEntries = computed(() => {
  const entries = [];

  if (viewportWidth.value <= 1180) {
    entries.push(
      { key: 'power', label: '重启', icon: 'AlertOutlined', action: handleRebootAction, topbarOverflow: true },
      { key: 'share', label: '一键分享', icon: 'ShareAltOutlined', action: handleTopbarShare, topbarOverflow: true },
    );
  }

  if (viewportWidth.value <= 1279) {
    entries.push({ key: 'refresh', label: '刷新实例', icon: 'ReloadOutlined', action: refreshSummary, topbarOverflow: true });
  }

  entries.push(
    { key: 'port', label: '端口映射', icon: 'ShareAltOutlined', targetKey: 'port' },
    { key: 'network-detail', label: '网络信息', icon: 'BranchesOutlined', targetKey: 'network-detail' },
    { key: 'system-control', label: '重装系统', icon: 'SettingOutlined', action: openReinstallModal },
    ...filteredPortableEntries.value,
  );

  return entries;
});
const remoteMethodBriefMap = {
  web: '浏览器直连，无需客户端。',
  rdp: 'Windows 下载 BAT，手机唤起 Remote App，其他设备下载 RDP 文件。',
  ssh: 'Windows 下载 SSH 脚本，手机和其他设备唤起 SSH 客户端。',
  vnc: '网页 VNC，适合救援排障。',
};
const remoteMethodNameMap = {
  rdp: 'RDP',
  ssh: 'SSH',
  vnc: 'VNC',
};
function normalizeHomeRemoteMethod(item) {
  const nextItem = normalizeRemoteLoginMethod(item, store.host);
  return {
    ...nextItem,
    name: remoteMethodNameMap[nextItem.key] || nextItem.name,
    description: remoteMethodBriefMap[nextItem.key] || nextItem.description,
    actionLabel: nextItem.key === 'rdp' || nextItem.key === 'ssh'
      ? getRemoteActionLabel(store.host)
      : nextItem.actionLabel,
  };
}

function createRdpHomeMethod(methods = []) {
  const rdpMethod = methods.find((item) => item?.key === 'rdp');
  return normalizeHomeRemoteMethod(rdpMethod || {
    key: 'rdp',
    name: 'RDP',
    description: remoteMethodBriefMap.rdp,
    primary: true,
    actionLabel: getRemoteActionLabel(store.host),
  });
}

const remotePrimaryMethods = computed(() => {
  const methods = store.pages?.vnc?.primaryMethods;
  if (isNarrowDashboardViewport.value) {
    return [createRdpHomeMethod(Array.isArray(methods) ? methods : [])];
  }

  if (Array.isArray(methods) && methods.length) {
    return methods.slice(0, 3).map(normalizeHomeRemoteMethod);
  }

  return [
    { key: 'web', name: '网页登录', description: '通过网页直连远程控制台，无需本地客户端。', primary: true, actionLabel: '登录' },
    normalizeRemoteLoginMethod({ key: 'rdp', name: 'RDP', description: remoteMethodBriefMap.rdp, primary: false, actionLabel: getRemoteActionLabel(store.host) }, store.host),
    { key: 'vnc', name: 'VNC', description: '使用 Web VNC 登录服务器。', primary: false, actionLabel: '登录' },
  ];
});
const maskedSystemPassword = computed(() => maskValue(store.host.systemPassword));
const maskedPanelPassword = computed(() => maskValue(store.host.panelPassword));
const passwordModalTitle = computed(() => (passwordMode.value === 'panel' ? '修改面板密码' : '修改系统密码'));
const passwordMaxLength = computed(() => (passwordMode.value === 'panel' ? 12 : 20));
const passwordPlaceholder = computed(() => (
  passwordMode.value === 'panel'
    ? '6-12 位数字、字母、下划线或短横线'
    : '8-20 位，至少三类字符'
));
const passwordRuleText = computed(() => (
  passwordMode.value === 'panel'
    ? '轻舟控制台密码规则：6-12 个数字、字母、下划线或短横线组合。'
    : '轻舟系统密码规则：8-20 个字符，至少包含大写字母、小写字母、数字、特殊符号中的三类。'
));
const expireDaysText = computed(() => {
  const value = String(store.host.expireDate || '').trim();
  if (!value) {
    return '';
  }

  const expireDate = new Date(`${value}T00:00:00`);
  if (Number.isNaN(expireDate.getTime())) {
    return '';
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const diffDays = Math.ceil((expireDate.getTime() - today.getTime()) / 86400000);

  if (diffDays < 0) {
    return '已到期';
  }

  if (diffDays === 0) {
    return '(今日到期)';
  }

  return `(剩余 ${diffDays} 天)`;
});

function syncSidebarForViewport() {
  viewportWidth.value = window.innerWidth;
  isDesktopViewport.value = viewportWidth.value >= 1024;

  if (isDesktopViewport.value) {
    mobileSidebarOpen.value = false;
    desktopSidebarOpen.value = true;
  }
}

function handleMenuToggle() {
  if (isDesktopViewport.value) {
    desktopSidebarCollapsed.value = !desktopSidebarCollapsed.value;
    return;
  }

  mobileSidebarOpen.value = true;
}

function handlePageSelect(key) {
  if (key === activePageKey.value) {
    if (!isDesktopViewport.value) {
      mobileSidebarOpen.value = false;
    }
    return;
  }

  activePageKey.value = key;

  if (!isDesktopViewport.value) {
    mobileSidebarOpen.value = false;
  }
}

function handleTutorialNavigate(key) {
  if (key) {
    handlePageSelect(key);
  }
}

function bindTutorialConsoleHelpers() {
  if (tutorialConsoleBound || typeof window === 'undefined') {
    return;
  }

  tutorialConsoleBound = true;
  window.mmuiStartTutorial = () => {
    tutorialRef.value?.start({ manual: true });
  };
  window.mmuiResetTutorial = () => {
    tutorialRef.value?.reset();
    tutorialRef.value?.start();
  };
}

function getTransitionStatus(action) {
  const map = {
    boot: { status: '开机中', powerState: 'starting' },
    start: { status: '开机中', powerState: 'starting' },
    shutdown: { status: '关机中', powerState: 'stopping' },
    close: { status: '关机中', powerState: 'stopping' },
    reboot: { status: '重启中', powerState: 'rebooting' },
    restart: { status: '重启中', powerState: 'rebooting' },
    reinstall: { status: '重装系统中', powerState: 'reinstalling', state: 4 },
  };

  return map[action] || null;
}

function hasReachedTransitionTarget(action, samples) {
  const kind = resolveHostStatusKind(store.host);
  if (kind === 'pending' || samples < 2) {
    return false;
  }

  if (action === 'shutdown' || action === 'close') {
    return kind === 'stopped' || kind === 'error';
  }

  if (action === 'reinstall') {
    return kind === 'running' || kind === 'error';
  }

  return kind === 'running' || kind === 'error';
}

function stopTransitionPolling() {
  window.clearTimeout(transitionPollTimer);
  transitionPollTimer = 0;
  transitionPollToken += 1;
}

function startTransitionPolling(action, options = {}) {
  const token = ++transitionPollToken;
  const interval = options.interval || 1600;
  const maxSamples = options.maxSamples || (action === 'reinstall' ? 80 : 36);
  let samples = 0;

  window.clearTimeout(transitionPollTimer);

  const tick = async () => {
    if (token !== transitionPollToken) {
      return;
    }

    samples += 1;
    try {
      await store.refreshState();
    } catch (error) {
      console.debug('MMUI transition state polling failed.', error);
    }

    if (token !== transitionPollToken) {
      return;
    }

    if (hasReachedTransitionTarget(action, samples) || samples >= maxSamples) {
      transitionPollTimer = 0;
      return;
    }

    transitionPollTimer = window.setTimeout(tick, interval);
  };

  transitionPollTimer = window.setTimeout(tick, 250);
}

function beginHostTransition(action) {
  const status = getTransitionStatus(action);
  if (status) {
    store.setHostRuntimeStatus(status);
  }
  startTransitionPolling(action);
}

async function handleRebootAction() {
  await runWithActionLoading('power:reboot', async () => {
    try {
      beginHostTransition('reboot');
      await store.powerAction('reboot');
      startTransitionPolling('reboot');
      message.success('重启请求已发送');
    } catch (error) {
      stopTransitionPolling();
      await pollHostState();
      message.error(error instanceof Error ? error.message : '重启请求失败');
    }
  });
}

function openPowerModal() {
  powerModalOpen.value = true;
}

async function submitPowerAction(action) {
  await runWithActionLoading(`power:${action}`, async () => {
    try {
      beginHostTransition(action);
      await store.powerAction(action);
      startTransitionPolling(action);
      message.success(action === 'boot' ? '开机请求已发送' : '关机请求已发送');
      powerModalOpen.value = false;
    } catch (error) {
      stopTransitionPolling();
      await pollHostState();
      message.error(error instanceof Error ? error.message : '电源操作失败');
    }
  });
}

function openReinstallModal() {
  reinstallModalOpen.value = true;
  reinstallStep.value = 0;
  selectedReinstallFamilyTitle.value = reinstallFamilies.value[0]?.title || '';
  selectedReinstallOption.value = reinstallFamilies.value[0]?.options?.[0] || '';
  reinstallPassword.value = store.host.systemPassword || '';
}

function closeReinstallModal() {
  reinstallModalOpen.value = false;
  reinstallStep.value = 0;
}

function selectReinstallFamily(title) {
  selectedReinstallFamilyTitle.value = title;
  const target = reinstallFamilies.value.find((family) => family.title === title);
  selectedReinstallOption.value = target?.options?.[0] || '';
}

function goReinstallNextStep() {
  if (!selectedReinstallOption.value) {
    message.warning('请先选择系统镜像');
    return;
  }

  reinstallStep.value = 1;
}

async function submitReinstall() {
  const errorMessage = validateSystemPassword(reinstallPassword.value);
  if (errorMessage) {
    message.warning(errorMessage);
    return;
  }

  await runWithActionLoading('system:reinstall', async () => {
    try {
      beginHostTransition('reinstall');
      await store.reinstallSystem({
        image: selectedReinstallOption.value,
        password: reinstallPassword.value,
      });
      startTransitionPolling('reinstall', { interval: 2200, maxSamples: 100 });
      message.success(`已提交重装请求：${selectedReinstallOption.value}`);
      reinstallModalOpen.value = false;
      reinstallStep.value = 0;
    } catch (error) {
      stopTransitionPolling();
      await pollHostState();
      message.error(error instanceof Error ? error.message : '提交重装请求失败');
    }
  });
}

function openPasswordModal(mode) {
  passwordMode.value = mode === 'panel' ? 'panel' : 'system';
  passwordDraft.value = passwordMode.value === 'panel'
    ? (store.host.panelPassword || '')
    : (store.host.systemPassword || '');
  passwordModalOpen.value = true;
}

function validatePanelPassword(value) {
  const text = String(value || '').trim();
  if (!/^[A-Za-z0-9_-]{6,12}$/.test(text)) {
    return '面板密码需为 6-12 位数字、字母、下划线或短横线组合';
  }
  return '';
}

function validateSystemPassword(value) {
  const text = String(value || '').trim();
  if (text.length < 8 || text.length > 20) {
    return '系统密码需为 8-20 个字符';
  }

  const classCount = [
    /[A-Z]/,
    /[a-z]/,
    /\d/,
    /[^A-Za-z0-9]/,
  ].filter((pattern) => pattern.test(text)).length;

  if (classCount < 3) {
    return '系统密码需至少包含大写字母、小写字母、数字、特殊符号中的三类';
  }

  return '';
}

function validatePasswordByMode(mode, value) {
  return mode === 'panel' ? validatePanelPassword(value) : validateSystemPassword(value);
}

async function submitPasswordUpdate() {
  const nextPassword = String(passwordDraft.value || '').trim();
  const errorMessage = validatePasswordByMode(passwordMode.value, nextPassword);
  if (errorMessage) {
    message.warning(errorMessage);
    return;
  }

  await runWithActionLoading(`password:${passwordMode.value}`, async () => {
    try {
      if (passwordMode.value === 'panel') {
        await store.updatePanelPassword(nextPassword);
        message.success('面板密码已更新');
      } else {
        await store.updateSystemPassword(nextPassword);
        message.success('系统密码已更新');
      }
      passwordModalOpen.value = false;
    } catch (error) {
      message.error(error instanceof Error ? error.message : '密码更新失败');
    }
  });
}

async function copyValue(value, label) {
  const text = String(value || '').trim();
  if (!text || text === '-') {
    message.warning(`暂无可复制的${label}`);
    return;
  }

  await copyTextToClipboard(text, `已复制${label}`);
}

async function copyTextToClipboard(text, successMessage) {
  try {
    await navigator.clipboard.writeText(text);
    message.success(successMessage);
  } catch {
    const input = document.createElement('textarea');
    input.value = text;
    input.setAttribute('readonly', 'readonly');
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    message.success(successMessage);
  }
}

function openUrl(url, emptyMessage) {
  const target = String(url || '').trim();
  if (!target) {
    message.info(emptyMessage);
    return;
  }

  window.open(target, '_blank');
}

async function handleRemotePrimaryAction(item) {
  if (item?.key === 'vnc') {
    await openVncConsole();
    return;
  }

  if (item?.key === 'rdp' || item?.key === 'ssh') {
    await triggerHostRemoteAccess(store.host);
    return;
  }

  handlePageSelect('vnc');
}

async function openVncConsole() {
  await runWithActionLoading('remote:vnc', async () => {
    try {
      openUrl(await store.openVnc(), 'VNC 控制台暂未返回可打开链接');
    } catch (error) {
      message.error(error instanceof Error ? error.message : 'VNC 控制台打开失败');
    }
  });
}

function downloadRdpLoginScript() {
  return triggerHostRemoteAccess(store.host);
}

function buildShareInfoText() {
  const host = store.host || {};
  const panelHost = String(host.panelAddress || host.panelDomain || window.location.host || '').trim();
  const panelUser = String(host.panelUser || 'www').trim();

  return [
    'VPS Information',
    '============',
    `核心：${displayShareValue(host.cpu, 0)}核心`,
    `内存：${displayShareValue(host.memory, 0)}GB`,
    `带宽：${displayShareValue(host.bandwidth, 0)}MB`,
    `数据盘：${displayShareValue(host.disk, 0)}GB`,
    `地区：${displayShareValue(host.areaName, '-')}`,
    '============',
    `系统：${displayShareValue(host.osName, '-')}`,
    `地址：${displayShareValue(host.remoteAddress, '-')}`,
    `账号：${systemUser.value}`,
    `密码：${displayShareValue(host.systemPassword, '-')}`,
    '============',
    `面板：${displayShareValue(panelHost, '-')}`,
    `账号：${displayShareValue(panelUser, 'www')}`,
    `密码：${displayShareValue(host.panelPassword, '-')}`,
    '',
    '===========',
    `${displayShareValue(host.buyDate, '-')}To${displayShareValue(host.expireDate, '-')}`,
  ].join('\n');
}

function displayShareValue(value, fallback) {
  const text = String(value ?? '').trim();
  return text && text !== '-' ? text : fallback;
}

async function refreshSummary() {
  await runWithActionLoading('refresh:summary', async () => {
    try {
      await store.refreshFromPhp();
    } catch (error) {
      message.error(error instanceof Error ? error.message : '刷新失败');
    }
  });
}

async function refreshStatusMonitor() {
  await runWithActionLoading('refresh:monitor', async () => {
    try {
      await store.refreshMonitor();
    } catch (error) {
      message.error(error instanceof Error ? error.message : '监控刷新失败');
    }
  });
}

function resolveQuickIcon(iconName) {
  return quickIconMap[iconName] || ApiOutlined;
}

function resolveReinstallFamilyType(family) {
  const text = [
    family?.type,
    family?.key,
    family?.title,
    family?.subtitle,
    family?.glyph,
  ].filter(Boolean).join(' ').toLowerCase();

  if (text.includes('windows') || text.includes('win')) return 'windows';
  if (text.includes('ubuntu')) return 'ubuntu';
  if (text.includes('fedora')) return 'fedora';
  if (text.includes('arch')) return 'arch';
  if (text.includes('rocky')) return 'rocky';
  if (text.includes('alma')) return 'alma';
  if (text.includes('centos')) return 'centos';
  if (text.includes('debian')) return 'debian';
  if (text.includes('linux')) return 'linux';
  return 'generic';
}

function resolveReinstallFamilyIcon(family) {
  const familyType = resolveReinstallFamilyType(family);
  if (familyType === 'windows') return FlatWindowsIcon;
  if (familyType === 'ubuntu') return UbuntuIcon;
  if (familyType === 'debian') return DebianIcon;
  if (familyType === 'centos') return CentosIcon;
  if (familyType === 'fedora') return FedoraIcon;
  if (familyType === 'arch') return ArchlinuxIcon;
  if (familyType === 'rocky') return RockylinuxIcon;
  if (familyType === 'alma') return AlmalinuxIcon;
  if (familyType === 'linux') return LinuxIcon;
  return DesktopOutlined;
}

function resolvePortableIcon(iconName) {
  return portableIconMap[iconName] || DesktopOutlined;
}

function resolveRemoteIcon(key) {
  return remoteIconMap[key] || remoteConsoleGraphic;
}

function normalizeNetworkText(value) {
  return String(value || '').replace(/^[^：:]+[：:]\s*/, '').trim();
}

function splitNetworkCell(input, fallbackLabel = '') {
  const text = String(input ?? '').trim();
  const match = text.match(/^([^：:]+)[：:]\s*(.*)$/);

  if (match) {
    return {
      label: match[1].trim(),
      value: match[2].trim(),
    };
  }

  return {
    label: fallbackLabel,
    value: text,
  };
}

function displayNetworkValue(value) {
  const text = String(value || '').trim();
  return text || '-';
}

function hasNetworkValue(value) {
  const text = String(value || '').trim();
  return Boolean(text && text !== '-');
}

function networkTables() {
  return Array.isArray(store.pages?.network?.tables) ? store.pages.network.tables : [];
}

function networkCells() {
  const cells = [];

  for (const table of networkTables()) {
    const rows = Array.isArray(table.rows) ? table.rows : [];
    const columns = Array.isArray(table.columns) && table.columns.length
      ? table.columns
      : Object.keys(rows[0] || {});

    for (const row of rows) {
      for (const column of columns) {
        cells.push(splitNetworkCell(row?.[column], column));
      }
    }
  }

  return cells;
}

function matchesNetworkLabel(input, labels) {
  const text = String(input || '').toLowerCase();
  return labels.some((label) => text.includes(String(label).toLowerCase()));
}

function findNetworkValue(labels) {
  const cell = networkCells().find((item) => (
    matchesNetworkLabel(item.label, labels) && hasNetworkValue(item.value)
  ));

  if (cell) {
    return normalizeNetworkText(cell.value);
  }

  return '';
}

function findNetworkRelatedValue(labels) {
  for (const table of networkTables()) {
    const columns = Array.isArray(table.columns) && table.columns.length
      ? table.columns
      : Object.keys((table.rows || [])[0] || {});

    for (const row of table.rows || []) {
      const cells = columns.map((column) => splitNetworkCell(row?.[column], column));
      const matched = cells.some((cell) => matchesNetworkLabel(cell.label, labels));

      if (matched) {
        const usageCell = cells.find((cell) => matchesNetworkLabel(cell.label, ['月使用', '使用', '已用']) && hasNetworkValue(cell.value));
        return normalizeNetworkText(usageCell?.value || cells[1]?.value || cells[0]?.value);
      }
    }
  }

  return '';
}

function readTrafficLimit() {
  const host = store.host || {};
  const page = store.pages?.network || {};
  return page.trafficLimit
    ?? page.traffic?.limit
    ?? page.flowLimit
    ?? page.flow?.limit
    ?? host.trafficLimit
    ?? host.traffic
    ?? host.flowLimit
    ?? host.flow_limit
    ?? '';
}

function parseDataSize(value) {
  const text = String(value || '').trim();
  const match = text.match(/([\d.]+)\s*(TB|GB|MB|KB)/i);

  if (!match) {
    return 0;
  }

  const amount = Number(match[1]);
  const unit = match[2].toUpperCase();
  const rateMap = {
    KB: 1 / 1024,
    MB: 1,
    GB: 1024,
    TB: 1024 * 1024,
  };

  return amount * (rateMap[unit] || 1);
}

function parseTrafficLimitSize(value) {
  const text = String(value || '').trim().replace(/,/g, '');
  if (!text || text === '-') {
    return null;
  }

  if (/^\d+(\.\d+)?$/.test(text)) {
    return Number(text) * 1024;
  }

  return parseDataSize(text);
}

function displayTrafficLimitValue(value) {
  const text = String(value || '').trim();
  if (!text || text === '-' || Number(text) === 0 || parseTrafficLimitSize(text) === 0) {
    return '无限制';
  }

  if (/^\d+(\.\d+)?$/.test(text)) {
    return `${Number(Number(text).toFixed(1)).toString()} GB`;
  }

  return text;
}

function formatDataSize(valueMb) {
  if (valueMb >= 1024 * 1024) {
    return `${(valueMb / 1024 / 1024).toFixed(1)} TB`;
  }

  if (valueMb >= 1024) {
    return `${(valueMb / 1024).toFixed(1)} GB`;
  }

  return `${Math.round(valueMb)} MB`;
}

function setActiveFeature(index) {
  const count = quickCards.value.length;
  if (!count) {
    activeFeatureIndex.value = 0;
    return;
  }

  activeFeatureIndex.value = ((index % count) + count) % count;
  restartFeatureAutoPlay();
}

function showPreviousFeature() {
  setActiveFeature(activeFeatureIndex.value - 1);
}

function showNextFeature() {
  setActiveFeature(activeFeatureIndex.value + 1);
}

function restartFeatureAutoPlay() {
  window.clearInterval(featureAutoTimer);
  featureAutoTimer = window.setInterval(() => {
    if (quickCards.value.length > 1 && activePageKey.value === 'panel') {
      activeFeatureIndex.value = (activeFeatureIndex.value + 1) % quickCards.value.length;
    }
  }, 5000);
}

function handlePortableEntry(item) {
  if (item?.key === 'vnc') {
    openVncConsole();
    moreMenuOpen.value = false;
    return;
  }

  if (item?.key === 'rescue') {
    handlePageSelect('system-control');
    bootModalRequest.value += 1;
    moreMenuOpen.value = false;
    return;
  }

  const targetKey = portableRouteMap[item.key];
  if (targetKey) {
    handlePageSelect(targetKey);
    moreMenuOpen.value = false;
    return;
  }

  message.info(`${item.label} 未提供可执行适配动作`);
  moreMenuOpen.value = false;
}

function handlePortableMenuClick(menuInfo) {
  const key = menuInfo?.key;
  if (!key) {
    return;
  }

  const target = filteredPortableEntries.value.find((entry) => entry.key === key);
  if (target) {
    handlePortableEntry(target);
  }
}

async function handleTopbarShare() {
  await copyTextToClipboard(buildShareInfoText(), '已复制 VPS 信息');
}

function handleTopbarMoreClick(menuInfo) {
  const key = menuInfo?.key;
  if (!key) {
    return;
  }

  const target = topbarMoreEntries.value.find((entry) => entry.key === key);
  if (!target) {
    return;
  }

  if (target.action) {
    target.action();
    moreMenuOpen.value = false;
    return;
  }

  if (target.targetKey) {
    handlePageSelect(target.targetKey);
    moreMenuOpen.value = false;
    return;
  }

  handlePortableEntry(target);
}

function getMorePopupContainer(triggerNode) {
  return document.body;
}

function getLastMetric(values) {
  if (!Array.isArray(values) || !values.length) {
    return 0;
  }

  return Number(values[values.length - 1] || 0);
}

function maskValue(value) {
  const text = String(value || '');
  return text ? '•'.repeat(5) : '-';
}

watch(
  () => [
    getLastMetric(store.monitors.cpu),
    getLastMetric(store.monitors.memory),
    getLastMetric(store.monitors.network),
    store.monitors.labels?.length || 0,
  ].join('|'),
  (value, oldValue) => {
    if (!oldValue || value === oldValue) {
      return;
    }

    statusCardUpdating.value = false;
    window.clearTimeout(statusUpdateTimer);
    requestAnimationFrame(() => {
      statusCardUpdating.value = true;
      statusUpdateTimer = window.setTimeout(() => {
        statusCardUpdating.value = false;
        statusUpdateTimer = 0;
      }, 520);
    });
  },
);

watch(
  () => ({
    cpu: percentToRatio(latestCpu.value),
    memory: percentToRatio(latestMemory.value),
    network: percentToRatio(latestNetworkPercent.value),
  }),
  (targets) => {
    animateStatusProgress(targets, hasAnimatedStatusProgress ? 560 : 720);
    hasAnimatedStatusProgress = true;
  },
  { immediate: true },
);

async function pollHostState() {
  try {
    await store.refreshState();
  } catch (error) {
    console.debug('MMUI host state polling failed.', error);
  }
}

async function pollMonitorSample() {
  try {
    await store.refreshMonitor();
  } catch (error) {
    console.debug('MMUI monitor polling failed.', error);
  }
}

function startQzRuntimePolling() {
  window.clearInterval(statePollTimer);
  window.clearInterval(monitorPollTimer);
  pollHostState();
  pollMonitorSample();
  statePollTimer = window.setInterval(pollHostState, 3000);
  monitorPollTimer = window.setInterval(pollMonitorSample, 8000);
}

onMounted(() => {
  bindTutorialConsoleHelpers();
  store.load().finally(() => {
    startQzRuntimePolling();
  });
  syncSidebarForViewport();
  restartFeatureAutoPlay();
  window.addEventListener('resize', syncSidebarForViewport);
});

onBeforeUnmount(() => {
  window.clearInterval(featureAutoTimer);
  window.clearInterval(statePollTimer);
  window.clearInterval(monitorPollTimer);
  window.clearTimeout(transitionPollTimer);
  window.clearTimeout(statusUpdateTimer);
  window.cancelAnimationFrame(statusProgressFrame);
  window.removeEventListener('resize', syncSidebarForViewport);
  if (typeof window !== 'undefined') {
    delete window.mmuiStartTutorial;
    delete window.mmuiResetTutorial;
  }
});
</script>

<style scoped>
.mmui-page-width {
  width: 100%;
  margin: 0;
}

.mmui-page-tab-enter-active {
  --mmui-page-motion-ease: cubic-bezier(0.16, 1, 0.3, 1);
  --mmui-card-motion-duration: 240ms;
  --mmui-card-motion-delay: 0ms;
  transition:
    opacity 180ms ease-out,
    transform 240ms var(--mmui-page-motion-ease);
  will-change: opacity, transform;
}

.mmui-page-tab-leave-active {
  transition:
    opacity 70ms ease-in,
    transform 70ms ease-in;
  pointer-events: none;
  will-change: opacity, transform;
}

.mmui-page-tab-enter-from {
  opacity: 0;
  transform: translate3d(0, 6px, 0);
}

.mmui-page-tab-leave-to {
  opacity: 0;
  transform: translate3d(0, -2px, 0);
}

.mmui-page-tab-enter-to,
.mmui-page-tab-leave-from {
  opacity: 1;
  transform: translateZ(0);
}

.dashboard-page.mmui-page-tab-enter-active .home-card,
.mmui-subpage.mmui-page-tab-enter-active :deep(.home-card),
.mmui-subpage.mmui-page-tab-enter-active :deep(.ant-card),
.mmui-subpage.mmui-page-tab-enter-active :deep(.resource-page__table-panel),
.mmui-subpage.mmui-page-tab-enter-active :deep(.remote-page__panel),
.mmui-subpage.mmui-page-tab-enter-active :deep(.system-page__card) {
  animation: mmui-card-soft-enter var(--mmui-card-motion-duration) var(--mmui-page-motion-ease) both;
  animation-delay: var(--mmui-card-motion-delay);
  will-change: opacity, transform;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__overview {
  --mmui-card-motion-delay: 18ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__remote-card {
  --mmui-card-motion-delay: 30ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-card {
  --mmui-card-motion-delay: 36ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__account-card {
  --mmui-card-motion-delay: 46ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__network-card {
  --mmui-card-motion-delay: 54ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__feature-card {
  --mmui-card-motion-delay: 64ms;
}

@keyframes mmui-card-soft-enter {
  0% {
    opacity: 0;
    transform: translate3d(0, 10px, 0) scale(0.992);
  }

  100% {
    opacity: 1;
    transform: translateZ(0) scale(1);
  }
}

@media (max-width: 640px) {
  .mmui-page-tab-enter-active {
    transition:
      opacity 180ms ease-out,
      transform 320ms var(--mmui-page-motion-ease);
  }

  .mmui-page-tab-enter-from {
    opacity: 0;
    transform: translate3d(0, -10px, 0) scale(0.992);
  }

  .mmui-subpage.mmui-page-tab-enter-active :deep(.home-card),
  .mmui-subpage.mmui-page-tab-enter-active :deep(.ant-card),
  .mmui-subpage.mmui-page-tab-enter-active :deep(.resource-page__table-panel),
  .mmui-subpage.mmui-page-tab-enter-active :deep(.remote-page__panel),
  .mmui-subpage.mmui-page-tab-enter-active :deep(.system-page__card) {
    animation-name: mmui-mobile-page-card-enter;
    animation-duration: 320ms;
  }
}

@keyframes mmui-mobile-page-card-enter {
  0% {
    opacity: 0;
    transform: translate3d(0, -10px, 0) scale(0.992);
  }

  100% {
    opacity: 1;
    transform: translateZ(0) scale(1);
  }
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__topbar-icon {
  animation: dashboard-icon-pop 360ms var(--mmui-page-motion-ease) 70ms both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__topbar-copy,
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__topbar-actions {
  animation: dashboard-soft-slide 320ms var(--mmui-page-motion-ease) 86ms both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-row,
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__account-row {
  animation: dashboard-row-reveal 300ms var(--mmui-page-motion-ease) both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-row:nth-child(1),
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__account-row:nth-child(1) {
  animation-delay: 110ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-row:nth-child(2),
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__account-row:nth-child(2) {
  animation-delay: 128ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-row:nth-child(3),
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__account-row:nth-child(3) {
  animation-delay: 146ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-row:nth-child(4),
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__account-row:nth-child(4) {
  animation-delay: 164ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-row:nth-child(5),
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__account-row:nth-child(5) {
  animation-delay: 182ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-art {
  animation: dashboard-server-art-enter 520ms var(--mmui-page-motion-ease) 96ms both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__visual-screen::after {
  animation: dashboard-art-sheen 620ms ease-out 160ms both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-circle-value {
  animation: dashboard-value-pop 300ms var(--mmui-page-motion-ease) 220ms both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span {
  animation: dashboard-wave-bar 360ms var(--mmui-page-motion-ease) both;
  transform-origin: bottom;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(1) { animation-delay: 160ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(2) { animation-delay: 174ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(3) { animation-delay: 188ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(4) { animation-delay: 202ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(5) { animation-delay: 216ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(6) { animation-delay: 230ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(7) { animation-delay: 244ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(8) { animation-delay: 258ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(9) { animation-delay: 272ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(10) { animation-delay: 286ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(11) { animation-delay: 300ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(12) { animation-delay: 314ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(13) { animation-delay: 328ms; }
.dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span:nth-child(14) { animation-delay: 342ms; }

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__remote-item {
  animation: dashboard-remote-item-enter 340ms var(--mmui-page-motion-ease) both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__remote-item:nth-child(1) {
  animation-delay: 120ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__remote-item:nth-child(2) {
  animation-delay: 146ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__remote-item:nth-child(3) {
  animation-delay: 172ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__network-item {
  animation: dashboard-network-unit 320ms var(--mmui-page-motion-ease) both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__network-primary {
  animation-delay: 132ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__network-metric--bandwidth {
  animation-delay: 156ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__network-metric--traffic {
  animation-delay: 180ms;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__network-bar i {
  animation: dashboard-progress-fill 480ms var(--mmui-page-motion-ease) 260ms both;
  transform-origin: left center;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__quick-copy {
  animation: dashboard-soft-slide 340ms var(--mmui-page-motion-ease) 150ms both;
}

.dashboard-page.mmui-page-tab-enter-active .dashboard-page__quick-graphic {
  animation: dashboard-quick-graphic 420ms var(--mmui-page-motion-ease) 170ms both;
}

@keyframes dashboard-soft-slide {
  0% {
    opacity: 0;
    transform: translate3d(8px, 0, 0);
  }

  100% {
    opacity: 1;
    transform: translateZ(0);
  }
}

@keyframes dashboard-row-reveal {
  0% {
    opacity: 0;
    transform: translate3d(0, 7px, 0);
  }

  100% {
    opacity: 1;
    transform: translateZ(0);
  }
}

@keyframes dashboard-icon-pop {
  0% {
    opacity: 0;
    transform: scale(0.84) rotate(-6deg);
  }

  62% {
    opacity: 1;
    transform: scale(1.04) rotate(0);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes dashboard-server-art-enter {
  0% {
    opacity: 0;
    transform: scale(1.08) translate3d(12px, 10px, 0);
  }

  100% {
    opacity: 1;
    transform: scale(1.16) translate3d(4px, -2px, 0);
  }
}

@keyframes dashboard-art-sheen {
  0% {
    opacity: 0;
    transform: translateX(-120%) skewX(-18deg);
  }

  34% {
    opacity: 0.26;
  }

  100% {
    opacity: 0;
    transform: translateX(120%) skewX(-18deg);
  }
}

@keyframes dashboard-value-pop {
  0% {
    opacity: 0;
    transform: scale(0.9);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes dashboard-wave-bar {
  0% {
    height: 3px;
    opacity: 0;
  }

  100% {
    height: max(3px, calc(var(--wave-height) * var(--bar-scale)));
    opacity: 1;
  }
}

@keyframes dashboard-remote-item-enter {
  0% {
    opacity: 0;
    transform: translate3d(0, 8px, 0) scale(0.985);
  }

  100% {
    opacity: 1;
    transform: translateZ(0) scale(1);
  }
}

@keyframes dashboard-network-unit {
  0% {
    opacity: 0;
    transform: translate3d(0, 6px, 0);
  }

  100% {
    opacity: 1;
    transform: translateZ(0);
  }
}

@keyframes dashboard-progress-fill {
  0% {
    transform: scaleX(0);
  }

  100% {
    transform: scaleX(1);
  }
}

@keyframes dashboard-status-wave-update {
  0% {
    opacity: 0.46;
  }

  56% {
    opacity: 1;
  }

  100% {
    opacity: 1;
  }
}

@keyframes dashboard-quick-graphic {
  0% {
    opacity: 0;
    transform: translate3d(10px, 0, 0) scale(0.9);
  }

  70% {
    opacity: 1;
    transform: translateZ(0) scale(1.04);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

.dashboard-page {
  --dashboard-gap: var(--mmui-space-2);
  --dashboard-card-padding: var(--mmui-card-padding);
  --dashboard-head-height: var(--mmui-card-head-height);
  --dashboard-home-columns: minmax(0, 1.35fr) minmax(320px, 0.9fr);
  --dashboard-row-height: var(--mmui-row-height);
  --dashboard-status-ring-size: 122px;
  display: grid;
  gap: var(--dashboard-gap);
  color: var(--mmui-text);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
}

.dashboard-page__topbar {
  padding: 0;
  overflow: visible;
}

.dashboard-page__topbar-main {
  /* Keep the topbar nearly flush with the card edges. Do not increase the 4px side padding. */
  display: grid;
  grid-template-columns: minmax(0, 1fr) max-content;
  align-items: center;
  gap: var(--mmui-space-3);
  min-height: 78px;
  padding: 14px 4px;
  overflow: hidden;
}

.dashboard-page__topbar-info {
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr);
  align-items: center;
  gap: var(--mmui-space-2);
  min-width: 0;
}

.dashboard-page__topbar-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 54px;
  height: 54px;
  color: #ffffff;
  font-size: 28px;
  border-radius: 8px;
  background: var(--mmui-accent-blue);
  box-shadow: 0 8px 18px rgba(0, 102, 238, 0.22);
}

.dashboard-page__topbar-icon :deep(svg) {
  display: block;
  width: 1em;
  height: 1em;
}

.dashboard-page__topbar-copy {
  display: grid;
  gap: 8px;
  min-width: 0;
}

.dashboard-page__topbar-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.dashboard-page__topbar-name {
  color: var(--mmui-card-title);
  font-size: 24px;
  font-weight: var(--mmui-font-weight-bold);
  line-height: var(--mmui-line-height-tight);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dashboard-page__topbar-tag {
  margin: 0 !important;
  padding: 0 10px !important;
  border-radius: 999px;
  font-size: var(--mmui-font-size-caption);
  line-height: 22px;
}

.dashboard-page__topbar-tag--status {
  color: rgba(255, 255, 255, 0.62) !important;
  background: rgba(255, 255, 255, 0.07) !important;
  border-color: rgba(255, 255, 255, 0.13) !important;
}

.dashboard-page__topbar-tag--status.is-success {
  color: #129a4d !important;
  background: rgba(21, 185, 104, 0.12) !important;
  border-color: rgba(21, 185, 104, 0.18) !important;
}

.dashboard-page__topbar-tag--status.is-danger {
  color: #d9363e !important;
  background: rgba(255, 77, 79, 0.12) !important;
  border-color: rgba(255, 77, 79, 0.24) !important;
}

.dashboard-page__topbar-tag--status.is-error {
  color: #ff4d4f !important;
  background: rgba(255, 77, 79, 0.16) !important;
  border-color: rgba(255, 77, 79, 0.32) !important;
}

.dashboard-page__topbar-tag--status.is-warning {
  color: #d48806 !important;
  background: rgba(250, 173, 20, 0.14) !important;
  border-color: rgba(250, 173, 20, 0.24) !important;
}

.dashboard-page__topbar-tag--status.is-neutral {
  color: rgba(255, 255, 255, 0.62) !important;
  background: rgba(255, 255, 255, 0.07) !important;
  border-color: rgba(255, 255, 255, 0.13) !important;
}

.dashboard-page__topbar-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  overflow: hidden;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-body);
  line-height: 22px;
  white-space: nowrap;
}

.dashboard-page__topbar-meta > span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dashboard-page__topbar-location {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.dashboard-page__topbar-ip {
  flex: 0 1 auto;
}

.dashboard-page__topbar-separator {
  color: color-mix(in srgb, var(--mmui-text-muted) 70%, transparent);
}

.dashboard-page__topbar-copy-btn {
  width: 24px !important;
  min-width: 24px !important;
  height: 24px !important;
  padding: 0 !important;
  color: var(--mmui-text-muted) !important;
}

.dashboard-page__topbar-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  justify-self: end;
  flex-wrap: nowrap;
  gap: 12px;
  width: max-content;
  min-width: max-content;
  white-space: nowrap;
  overflow: hidden;
}

.dashboard-page__topbar-actions :deep(.ant-btn) {
  min-height: 44px;
  padding-inline: 16px;
  border-radius: 8px;
  font-weight: var(--mmui-font-weight-semibold);
}

.dashboard-page__topbar-actions :deep(.ant-btn .anticon) {
  font-size: var(--mmui-font-size-title);
}

.dashboard-page__topbar-actions :deep(.ant-btn > span) {
  display: inline-flex;
  align-items: center;
  gap: var(--mmui-space-1);
}

.dashboard-page__remote-connect-btn {
  min-width: 112px;
  box-shadow: 0 8px 18px rgba(var(--mmui-accent-blue-rgb), 0.2);
}

.dashboard-page__topbar-action-btn {
  min-width: 106px;
}

.dashboard-page__topbar-refresh-btn {
  width: 44px;
  min-width: 44px !important;
  padding: 0 !important;
}

.dashboard-page__more-btn {
  min-width: 146px;
}

.dashboard-page__more-dropdown {
  z-index: 40;
}

:global(.dashboard-page__more-dropdown .dashboard-page__more-overflow-item) {
  display: none;
}

@media (max-width: 1279px) {
  :global(.dashboard-page__more-dropdown .dashboard-page__more-overflow-item--refresh) {
    display: flex;
  }
}

@media (max-width: 1180px) {
  :global(.dashboard-page__more-dropdown .dashboard-page__more-overflow-item--share) {
    display: flex;
  }
}

@media (max-width: 1180px) {
  :global(.dashboard-page__more-dropdown .dashboard-page__more-overflow-item--power) {
    display: flex;
  }
}

.dashboard-page__hero {
  display: grid;
  grid-template-columns: var(--dashboard-home-columns);
  grid-template-areas:
    'overview status'
    'remote account';
  grid-template-rows: auto minmax(190px, auto);
  gap: var(--dashboard-gap);
  align-items: stretch;
}

.dashboard-page__main {
  display: contents;
}

.dashboard-page__overview {
  container: dashboard-overview / inline-size;
  grid-area: overview;
  position: relative;
  display: grid;
  gap: var(--dashboard-gap);
  align-self: stretch;
  height: 100%;
  padding: 0;
  overflow: hidden;
}

.dashboard-page__overview-head,
.dashboard-page__card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mmui-space-2);
  min-height: var(--dashboard-head-height);
  padding: 0 var(--dashboard-card-padding);
  border-bottom: 0;
  background: transparent;
}

.dashboard-page__overview-head--server {
  min-height: 64px;
}

.dashboard-page__card-head-side {
  display: inline-flex;
  align-items: center;
  gap: var(--mmui-space-1);
  flex: 0 0 auto;
  justify-content: flex-end;
  margin-left: auto;
  min-width: 0;
}

.dashboard-page__overview-status {
  display: inline-flex;
  align-items: center;
  gap: var(--mmui-space-2);
  min-width: 0;
}

.dashboard-page__overview-status-text {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-font-weight-semibold);
  line-height: var(--mmui-line-height-tight);
}

.dashboard-page__overview-status-link {
  padding-inline: 0 !important;
  height: auto;
  color: var(--mmui-accent-blue) !important;
  font-size: var(--mmui-font-size-body);
}

.dashboard-page__overview-status-link:hover {
  color: color-mix(in srgb, var(--mmui-accent-blue) 82%, #ffffff) !important;
}

.dashboard-page__server-title {
  display: inline-flex;
  align-items: center;
  gap: var(--mmui-space-1);
  min-width: 0;
}

.dashboard-page__server-dot {
  width: 10px;
  min-width: 10px;
  height: 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.07);
  transition:
    background 180ms ease,
    box-shadow 180ms ease;
}

.dashboard-page__server-dot.is-success {
  background: #129a4d;
  box-shadow: 0 0 0 3px rgba(21, 185, 104, 0.14);
}

.dashboard-page__server-dot.is-danger {
  background: #d9363e;
  box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.16);
}

.dashboard-page__server-dot.is-error {
  background: #ff4d4f;
  box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.22);
}

.dashboard-page__server-dot.is-warning {
  background: #d48806;
  box-shadow: 0 0 0 3px rgba(250, 173, 20, 0.18);
}

.dashboard-page__server-dot.is-neutral {
  background: rgba(255, 255, 255, 0.38);
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.07);
}

.dashboard-page__server-name {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-font-weight-semibold);
}

.dashboard-page__server-tag {
  margin: 0 !important;
  border-radius: 999px;
}

.dashboard-page__server-refresh {
  cursor: pointer;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-title);
}

.dashboard-page__eyebrow,
.dashboard-page__card-subtitle {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-body);
}

.dashboard-page__card-title {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-text-headline-weight);
  line-height: var(--mmui-line-height-headline);
}

.dashboard-page__card-subtitle-link {
  padding-inline: 0 !important;
  color: var(--mmui-accent-blue) !important;
  font-weight: 500;
}

.dashboard-page__card-subtitle-link:hover {
  color: color-mix(in srgb, var(--mmui-accent-blue) 82%, #ffffff) !important;
}

.dashboard-page__overview-main {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(240px, 0.9fr);
  gap: var(--mmui-space-3);
  padding: 0 var(--dashboard-card-padding) var(--dashboard-card-padding);
  transition:
    grid-template-columns 0.14s ease-out,
    gap 0.14s ease-out;
}

.dashboard-page__server-copy {
  display: grid;
  gap: 0;
  align-content: start;
  padding: var(--mmui-space-1) 0 0;
}

.dashboard-page__server-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mmui-space-2);
  min-height: var(--dashboard-row-height);
  border-bottom: 1px solid var(--mmui-shell-border);
}

.dashboard-page__server-row:last-child {
  border-bottom: 0;
}

.dashboard-page__server-row span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
}

.dashboard-page__server-key {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  white-space: nowrap;
}

.dashboard-page__server-key-icon {
  flex: 0 0 auto;
  color: var(--mmui-accent-blue) !important;
  font-size: var(--mmui-font-size-title);
  opacity: 0.82;
}

.dashboard-page__server-row strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-text-body-size);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-text-body-line-height);
}

.dashboard-page__server-pop-text {
  display: block;
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}

.dashboard-page__text-popover {
  display: block;
  max-width: min(320px, 72vw);
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
  overflow-wrap: anywhere;
}

.dashboard-page__server-value {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--mmui-space-1);
  min-width: 0;
}

.dashboard-page__server-actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  flex: 0 0 auto;
  min-width: max-content;
}

.dashboard-page__server-row--os {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
}

.dashboard-page__server-row--password {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  column-gap: var(--mmui-space-2);
}

.dashboard-page__server-row--os .dashboard-page__server-value {
  display: inline-flex;
  justify-self: end;
  max-width: 100%;
}

.dashboard-page__server-row--password .dashboard-page__server-value {
  justify-self: stretch;
}

.dashboard-page__server-value--password {
  justify-content: flex-start;
}

.dashboard-page__server-value--password > strong {
  flex: 0 1 auto;
  min-width: 0;
  max-width: 96px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-page__server-value--password > strong.is-password-visible {
  max-width: 128px;
}

.dashboard-page__server-row--os strong {
  min-width: 0;
  text-align: right;
  white-space: nowrap;
}

.dashboard-page__server-row--os .dashboard-page__inline-link {
  flex: 0 0 auto;
}

.dashboard-page__server-value--expire {
  align-items: baseline;
  gap: var(--mmui-space-1);
}

.dashboard-page__server-value--expire strong {
  white-space: nowrap;
}

.dashboard-page__server-value--expire small {
  color: color-mix(in srgb, #2f9a43 88%, #ffffff);
  font-size: var(--mmui-font-size-caption);
  line-height: 1.4;
  white-space: nowrap;
}

.dashboard-page__server-row--stack {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  column-gap: var(--mmui-space-2);
  padding: 12px 0;
}

.dashboard-page__server-tags {
  display: flex;
  flex-wrap: nowrap;
  gap: 6px;
  min-width: 0;
  justify-content: flex-start;
  overflow-x: auto;
  overflow-y: hidden;
  overscroll-behavior-inline: contain;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}

.dashboard-page__server-tags::-webkit-scrollbar {
  display: none;
}

.dashboard-page__server-tags :deep(.dashboard-page__server-spec-tag) {
  flex: 0 0 auto;
  max-width: 100%;
  min-height: 22px;
  margin: 0 !important;
  padding: 0 8px !important;
  font-size: var(--mmui-font-size-caption);
  font-weight: var(--mmui-font-weight-medium);
  line-height: 20px;
  white-space: nowrap;
  border-radius: 999px;
  text-align: center;
  color: var(--mmui-card-title) !important;
  background: transparent !important;
  border: 1px solid var(--mmui-shell-border) !important;
  box-shadow: none !important;
}

.mmui-theme-root[data-mmui-theme='dark'] .dashboard-page__server-tags :deep(.dashboard-page__server-spec-tag) {
  color: var(--mmui-card-title) !important;
  background: transparent !important;
  border-color: var(--mmui-shell-border) !important;
}

.dashboard-page__inline-link {
  padding-inline: 0 !important;
}

.dashboard-page__visual {
  display: flex;
  align-items: stretch;
  min-width: 0;
  padding: var(--mmui-space-2) 0 0;
  opacity: 1;
  transform: translateX(0) scale(1);
  transform-origin: right center;
  visibility: visible;
  transition:
    opacity 0.1s ease-out,
    transform 0.14s ease-out,
    visibility 0s linear 0s;
}

.dashboard-page__visual-screen {
  position: relative;
  width: 100%;
  aspect-ratio: 363 / 236;
  min-height: 0;
  overflow: hidden;
  border: 0;
  border-radius: 0;
  background: transparent;
}

.dashboard-page__visual-screen::after {
  position: absolute;
  top: 8%;
  bottom: 8%;
  left: 0;
  width: 34%;
  content: '';
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.28), transparent);
  pointer-events: none;
  opacity: 0;
}

.dashboard-page__visual-screen.is-light-art {
  background:
    radial-gradient(circle at 54% 32%, rgba(var(--mmui-accent-blue-rgb), 0.12), transparent 42%);
}

.dashboard-page__visual-screen > img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  user-select: none;
  -webkit-user-drag: none;
}

.dashboard-page__visual-screen > .dashboard-page__server-art {
  object-fit: contain;
  transform: scale(1.16) translate(4px, -2px);
  filter: drop-shadow(0 18px 28px rgba(var(--mmui-accent-blue-rgb), 0.18));
}

.dashboard-page__visual-screen.is-dark-art > .dashboard-page__server-art {
  opacity: 0.84;
  filter:
    brightness(0.68)
    contrast(1.06)
    saturate(0.88)
    drop-shadow(0 18px 30px rgba(18, 38, 94, 0.3));
}

@container dashboard-overview (max-width: 620px) {
  .dashboard-page__overview-main {
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
  }

  .dashboard-page__server-copy {
    width: 100%;
  }

  .dashboard-page__server-row {
    display: grid;
    grid-template-columns: 112px minmax(0, 1fr);
    justify-content: initial;
    column-gap: 12px;
  }

  .dashboard-page__server-row--stack {
    align-items: center;
    padding: 10px 0;
  }

  .dashboard-page__server-value {
    display: inline-flex;
    flex-wrap: nowrap;
    column-gap: var(--mmui-space-1);
    width: 100%;
    align-items: center;
    justify-content: flex-start;
  }

  .dashboard-page__server-value > strong {
    flex: 0 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .dashboard-page__server-row--os .dashboard-page__server-value {
    justify-self: stretch;
  }

  .dashboard-page__server-row--os strong {
    text-align: left;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .dashboard-page__server-value .dashboard-page__inline-link,
  .dashboard-page__server-value .dashboard-page__copy-btn {
    margin-left: 0;
  }

  .dashboard-page__server-actions {
    flex: 0 0 auto;
  }

  .dashboard-page__server-value--expire {
    display: inline-flex;
    flex-wrap: nowrap;
    justify-content: flex-start;
  }

  .dashboard-page__server-value--expire strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .dashboard-page__server-tags {
    width: 100%;
    flex-wrap: nowrap;
    justify-content: flex-start;
    margin-left: 0;
    overflow-x: auto;
    overflow-y: hidden;
    overscroll-behavior-inline: contain;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }

  .dashboard-page__server-tags::-webkit-scrollbar {
    display: none;
  }

  .dashboard-page__visual {
    display: none;
  }
}

@container dashboard-overview (max-width: 354px) {
  .dashboard-page__server-value--expire small {
    display: none;
  }
}

.dashboard-page__side {
  display: contents;
}

.dashboard-page__status-card,
.dashboard-page__remote-card,
.dashboard-page__account-card,
.dashboard-page__portable,
.dashboard-page__network-card,
.dashboard-page__feature-card,
.dashboard-page__quick-card {
  position: relative;
  padding: 0;
  overflow: hidden;
}

.dashboard-page__status-card {
  grid-area: status;
  height: 100%;
}

.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span {
  animation: dashboard-status-wave-update 420ms var(--mmui-page-motion-ease) both;
}

.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(1) { animation-delay: 0ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(2) { animation-delay: 10ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(3) { animation-delay: 20ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(4) { animation-delay: 30ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(5) { animation-delay: 40ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(6) { animation-delay: 50ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(7) { animation-delay: 60ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(8) { animation-delay: 70ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(9) { animation-delay: 80ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(10) { animation-delay: 90ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(11) { animation-delay: 100ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(12) { animation-delay: 110ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(13) { animation-delay: 120ms; }
.dashboard-page__status-card.is-updating .dashboard-page__status-mini-wave span:nth-child(14) { animation-delay: 130ms; }

.dashboard-page__remote-card {
  grid-area: remote;
  height: 100%;
}

.dashboard-page__account-card {
  grid-area: account;
  align-self: stretch;
  height: 100%;
}

.dashboard-page__status-list,
.dashboard-page__account-list {
  display: grid;
  gap: 0;
  padding: var(--mmui-space-1) var(--dashboard-card-padding) var(--dashboard-card-padding);
}

.dashboard-page__account-list {
  align-content: start;
  grid-auto-rows: var(--dashboard-row-height);
}

.dashboard-page__status-list {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  padding: calc(var(--dashboard-status-ring-size) * 0.06) var(--dashboard-card-padding) var(--dashboard-card-padding);
}

.dashboard-page__status-item {
  display: grid;
  gap: var(--mmui-space-1);
  padding: var(--mmui-space-2) 0;
  border-bottom: 1px solid var(--mmui-shell-border);
}

.dashboard-page__status-item.is-circle {
  grid-template-columns: minmax(0, 1fr);
  gap: var(--mmui-space-2);
  align-content: start;
  justify-items: center;
  padding: 0 var(--mmui-space-1);
  border-bottom: 0;
  text-align: center;
}

.dashboard-page__status-item:last-child {
  border-bottom: 0;
}

.dashboard-page__status-circle {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: var(--dashboard-status-ring-size);
  height: var(--dashboard-status-ring-size);
}

.dashboard-page__status-circle-svg {
  width: var(--dashboard-status-ring-size);
  height: var(--dashboard-status-ring-size);
  overflow: visible;
  transform: rotate(-2deg);
}

.dashboard-page__status-circle-arc {
  stroke-dasharray: 226.2;
  stroke-dashoffset: 226.2;
  filter: drop-shadow(0 3px 8px color-mix(in srgb, currentColor 22%, transparent));
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
  transition:
    filter 240ms ease,
    opacity 180ms ease;
}

.dashboard-page__status-circle-value {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-section);
  font-weight: var(--mmui-font-weight-bold);
}

.dashboard-page__status-circle-meta {
  display: grid;
  gap: var(--mmui-space-1);
  min-width: 0;
  justify-items: center;
}

.dashboard-page__status-circle-head {
  display: inline-flex;
  align-items: center;
  gap: var(--mmui-space-1);
  min-width: 0;
}

.dashboard-page__status-circle-title {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-font-weight-semibold);
}

.dashboard-page__status-circle-unit {
  color: var(--mmui-text-muted);
  font-size: 12px;
  line-height: 1;
}

.dashboard-page__status-mini-wave {
  --wave-height: 18px;
  display: flex;
  align-items: end;
  justify-content: center;
  gap: 3px;
  width: 72px;
  height: var(--wave-height);
  opacity: 0.58;
}

.dashboard-page__status-mini-wave span {
  --bar-scale: 0.18;
  display: block;
  width: 3px;
  height: max(3px, calc(var(--wave-height) * var(--bar-scale)));
  border-radius: 999px;
  transition:
    height 520ms cubic-bezier(0.2, 0.84, 0.24, 1),
    opacity 240ms ease;
  will-change: height;
}

.dashboard-page__status-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mmui-space-2);
}

.dashboard-page__status-line span,
.dashboard-page__account-key {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
  white-space: nowrap;
}

.dashboard-page__account-key-icon {
  flex: 0 0 auto;
  color: var(--mmui-accent-blue);
  font-size: var(--mmui-font-size-title);
  opacity: 0.82;
}

.dashboard-page__status-line strong,
.dashboard-page__account-text,
.dashboard-page__account-system strong {
  color: var(--mmui-card-title);
  font-size: var(--mmui-text-body-size);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-text-body-line-height);
}

.dashboard-page__status-item small {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
}

.dashboard-page__account-row {
  display: grid;
  grid-template-columns: 118px minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--mmui-space-2);
  height: var(--dashboard-row-height);
  min-height: var(--dashboard-row-height);
  padding: 0;
  border-bottom: 1px solid var(--mmui-shell-border);
}

.dashboard-page__account-row:last-child {
  border-bottom: 0;
}

.dashboard-page__account-main {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  width: 100%;
  min-width: 0;
}

.dashboard-page__account-actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--mmui-space-1);
  min-width: 0;
  flex: 0 0 auto;
}

.dashboard-page__account-text {
  min-width: 0;
  text-align: right;
}

.dashboard-page__account-pop-text {
  display: block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}

.dashboard-page__mono {
  font-family: ui-monospace, Menlo, Consolas, monospace;
}

.dashboard-page__copy-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  min-width: 24px;
  height: 24px;
  padding: 0;
  color: var(--mmui-text-muted) !important;
}

.dashboard-page__copy-btn:hover {
  color: var(--mmui-accent-blue) !important;
}

.dashboard-page__account-os-btn.ant-btn[disabled] {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  min-width: 24px;
  height: 24px;
  padding: 0;
  color: var(--mmui-accent-blue) !important;
  cursor: default;
  background: rgba(var(--mmui-accent-blue-rgb), 0.1) !important;
  border: 0 !important;
  opacity: 1;
}

.dashboard-page__account-os-btn.ant-btn[disabled] :deep(.anticon),
.dashboard-page__account-os-btn.ant-btn[disabled] :deep(svg) {
  font-size: var(--mmui-font-size-title);
  width: 1em;
  height: 1em;
}

.dashboard-page__copy-btn--ghost {
  visibility: hidden;
  pointer-events: none;
}

.dashboard-page__lower-grid {
  display: grid;
  grid-template-columns: var(--dashboard-home-columns);
  gap: var(--dashboard-gap);
}

.dashboard-page__network-card,
.dashboard-page__feature-card {
  min-height: 156px;
}

.dashboard-page__network-content {
  display: flex;
  gap: 0;
  align-items: stretch;
  width: 100%;
  padding: 0 var(--dashboard-card-padding) var(--dashboard-card-padding);
}

.dashboard-page__network-item {
  display: grid;
  align-content: center;
  min-width: 0;
  min-height: 78px;
  padding: 0;
}

.dashboard-page__network-primary {
  flex: 1 1 0;
  gap: var(--mmui-space-1);
  padding-right: var(--dashboard-card-padding);
}

.dashboard-page__network-item + .dashboard-page__network-item {
  border-left: 1px solid var(--mmui-shell-border);
}

.dashboard-page__network-primary span,
.dashboard-page__network-metric span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-caption);
}

.dashboard-page__network-ip-row {
  display: flex;
  align-items: center;
  gap: var(--mmui-space-1);
  min-width: max-content;
}

.dashboard-page__network-ip-row strong {
  min-width: max-content;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-font-weight-semibold);
  line-height: 1.15;
}

.dashboard-page__network-primary small,
.dashboard-page__network-metric small {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
}

.dashboard-page__network-metric {
  flex: 1 1 0;
  padding-left: var(--dashboard-card-padding);
  gap: 6px;
}

.dashboard-page__network-metric--bandwidth {
  flex-grow: 1;
  padding-right: var(--dashboard-card-padding);
}

.dashboard-page__network-metric--traffic {
  flex-grow: 1;
}

.dashboard-page__network-metric strong {
  min-width: max-content;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-font-weight-semibold);
  font-family: ui-monospace, Menlo, Consolas, monospace;
  line-height: 1.1;
  white-space: nowrap;
}

.dashboard-page__network-metric small {
  white-space: nowrap;
}

.dashboard-page__network-bar {
  height: 6px;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(var(--mmui-accent-blue-rgb), 0.1);
}

.dashboard-page__network-bar i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--mmui-accent-blue);
}

.dashboard-page__feature-arrow {
  position: absolute !important;
  top: 50%;
  z-index: 4;
  width: 34px !important;
  min-width: 34px !important;
  height: 48px !important;
  padding: 0 !important;
  color: var(--mmui-text-muted) !important;
  background: transparent !important;
  border: 0 !important;
  box-shadow: none !important;
  transform: translateY(-50%);
}

.dashboard-page__feature-arrow :deep(.anticon) {
  font-size: 30px;
  line-height: 1;
}

.dashboard-page__feature-arrow:hover {
  color: var(--mmui-accent-blue) !important;
  background: transparent !important;
}

.dashboard-page__feature-arrow--prev {
  left: 14px;
}

.dashboard-page__feature-arrow--next {
  right: 14px;
}

.dashboard-page__feature-slide {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--dashboard-card-padding);
  min-height: 132px;
  padding: var(--dashboard-card-padding) 58px 38px;
  cursor: pointer;
}

.dashboard-page__feature-dots {
  position: absolute;
  left: 58px;
  right: 58px;
  bottom: 20px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.dashboard-page__feature-dots button {
  width: 100%;
  height: 6px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: color-mix(in srgb, var(--mmui-text-muted) 42%, transparent);
  cursor: pointer;
  transition:
    width 0.18s ease,
    background 0.18s ease;
}

.dashboard-page__feature-dots button.is-active {
  background: var(--mmui-accent-blue);
}

.dashboard-page__feature-card .dashboard-page__quick-copy {
  align-content: center;
  gap: 8px;
}

.dashboard-page__feature-card .dashboard-page__quick-arrow {
  display: none;
}

.dashboard-page__feature-card .dashboard-page__quick-media {
  justify-content: center;
  min-width: 104px;
  flex-basis: 104px;
}

.dashboard-page__feature-card .dashboard-page__quick-graphic {
  width: 104px;
  height: 104px;
}

.dashboard-page__feature-card .dashboard-page__quick-icon {
  font-size: 68px;
}

.dashboard-page__feature-card .dashboard-page__quick-asset {
  width: 96px;
  height: 96px;
}

.dashboard-page__quick-card {
  cursor: pointer;
  min-height: 128px;
  overflow: hidden;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.018), rgba(255, 255, 255, 0.008)),
    rgba(12, 17, 32, 0.82);
  transition:
    transform 0.18s ease,
    border-color 0.18s ease,
    box-shadow 0.18s ease;
}

.dashboard-page__quick-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 26px rgba(9, 14, 28, 0.18);
}

.dashboard-page__quick-content {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: var(--mmui-space-2);
  width: 100%;
  min-width: 0;
  padding: var(--dashboard-card-padding);
}

.dashboard-page__quick-copy {
  display: grid;
  align-content: start;
  gap: var(--mmui-space-1);
  min-width: 0;
  flex: 1 1 auto;
}

.dashboard-page__quick-head {
  display: flex;
  align-items: center;
  min-width: 0;
}

.dashboard-page__quick-arrow {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-body);
}

.dashboard-page__quick-title {
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-font-weight-semibold);
}

.dashboard-page__quick-subtitle {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-tight);
}

.dashboard-page__quick-note {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-body);
}

.dashboard-page__quick-media {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  min-width: 92px;
  flex: 0 0 92px;
}

.dashboard-page__quick-graphic {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 88px;
  height: 88px;
  border-radius: 16px;
  background: radial-gradient(circle at 50% 50%, rgba(var(--mmui-accent-blue-rgb), 0.14), transparent 62%);
}

.dashboard-page__quick-icon {
  position: relative;
  z-index: 1;
  font-size: 56px;
  color: rgba(var(--mmui-accent-blue-rgb), 0.86);
}

.dashboard-page__quick-asset {
  position: relative;
  z-index: 1;
  display: block;
  width: 80px;
  height: 80px;
  object-fit: contain;
  filter: drop-shadow(0 0 18px rgba(var(--mmui-accent-blue-rgb), 0.2));
}

.dashboard-page__quick-graphic.is-port .dashboard-page__quick-icon {
  color: var(--mmui-accent-blue);
  filter: drop-shadow(0 0 18px rgba(var(--mmui-accent-blue-rgb), 0.34));
}

.dashboard-page__quick-graphic.is-snapshot .dashboard-page__quick-icon {
  color: color-mix(in srgb, var(--mmui-accent-blue) 82%, #ffffff);
  filter: drop-shadow(0 0 18px rgba(var(--mmui-accent-blue-rgb), 0.28));
}

.dashboard-page__quick-graphic.is-backup .dashboard-page__quick-icon {
  color: color-mix(in srgb, var(--mmui-accent-blue) 68%, #ffffff);
  filter: drop-shadow(0 0 18px rgba(var(--mmui-accent-blue-rgb), 0.24));
}

.dashboard-page__quick-graphic.is-firewall .dashboard-page__quick-icon {
  color: color-mix(in srgb, var(--mmui-accent-blue) 72%, #ffffff);
  filter: drop-shadow(0 0 18px rgba(var(--mmui-accent-blue-rgb), 0.3));
}

.dashboard-page__power-modal,
.dashboard-page__password-modal,
.dashboard-page__reinstall-body {
  display: grid;
  gap: var(--mmui-space-2);
  min-width: 0;
}

.dashboard-page__modal-summary,
.dashboard-page__reinstall-head {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  min-width: 0;
  padding: 12px;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 6px;
  background: color-mix(in srgb, var(--mmui-card-surface) 94%, transparent);
}

.dashboard-page__modal-summary {
  grid-template-columns: auto minmax(0, 1fr) auto;
}

.dashboard-page__reinstall-quota.ant-tag {
  justify-self: start;
  width: auto;
  max-width: max-content;
  margin: 0 !important;
  padding: 0 10px !important;
  border-radius: 999px;
  font-size: var(--mmui-font-size-body);
  line-height: 24px;
}

.dashboard-page__modal-summary-icon,
.dashboard-page__power-action-icon,
.dashboard-page__reinstall-glyph {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  min-width: 36px;
  height: 36px;
  color: var(--mmui-accent-blue);
  border-radius: 8px;
  background: rgba(var(--mmui-accent-blue-rgb), 0.1);
}

.dashboard-page__reinstall-glyph {
  font-size: 20px;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, currentColor 16%, transparent);
}

.dashboard-page__reinstall-glyph.is-windows {
  color: #4f6bff;
  background: rgba(79, 107, 255, 0.12);
}

.dashboard-page__reinstall-glyph.is-ubuntu {
  color: #e95420;
  background: rgba(233, 84, 32, 0.13);
}

.dashboard-page__reinstall-glyph.is-centos {
  color: #8c5cf6;
  background: rgba(140, 92, 246, 0.13);
}

.dashboard-page__reinstall-glyph.is-fedora {
  color: #51a2da;
  background: rgba(81, 162, 218, 0.13);
}

.dashboard-page__reinstall-glyph.is-arch {
  color: #1793d1;
  background: rgba(23, 147, 209, 0.13);
}

.dashboard-page__reinstall-glyph.is-rocky {
  color: #10b981;
  background: rgba(16, 185, 129, 0.13);
}

.dashboard-page__reinstall-glyph.is-alma {
  color: #0b5cab;
  background: rgba(11, 92, 171, 0.13);
}

.dashboard-page__reinstall-glyph.is-debian {
  color: #d70a53;
  background: rgba(215, 10, 83, 0.12);
}

.dashboard-page__reinstall-glyph.is-linux {
  color: #f0a11a;
  background: rgba(240, 161, 26, 0.12);
}

.dashboard-page__reinstall-glyph.is-generic {
  color: var(--mmui-accent-blue);
  background: rgba(var(--mmui-accent-blue-rgb), 0.1);
}

.dashboard-page__modal-summary-main,
.dashboard-page__reinstall-head > div {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.dashboard-page__modal-summary-main span,
.dashboard-page__reinstall-head span,
.dashboard-page__reinstall-panel-head span,
.dashboard-page__confirm-row span {
  color: var(--mmui-text-muted);
  font-size: var(--mmui-font-size-footnote);
  line-height: var(--mmui-line-height-footnote);
}

.dashboard-page__modal-summary-main strong,
.dashboard-page__reinstall-head strong,
.dashboard-page__reinstall-panel-head strong,
.dashboard-page__confirm-row strong {
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-page__power-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.dashboard-page__power-action,
.dashboard-page__reinstall-family,
.dashboard-page__reinstall-option {
  display: grid;
  align-items: center;
  min-width: 0;
  color: inherit;
  text-align: left;
  appearance: none;
  border: 1px solid var(--mmui-shell-border);
  border-radius: 6px;
  background: transparent;
  cursor: pointer;
  transition:
    border-color 0.16s ease,
    background-color 0.16s ease,
    box-shadow 0.18s ease,
    transform 0.18s ease;
}

.dashboard-page__power-action {
  grid-template-columns: auto minmax(0, 1fr);
  gap: 10px;
  min-height: 82px;
  padding: 12px;
}

.dashboard-page__power-action:hover,
.dashboard-page__reinstall-family:hover,
.dashboard-page__reinstall-option:hover {
  border-color: color-mix(in srgb, var(--mmui-accent-blue) 38%, var(--mmui-shell-border));
  background: rgba(var(--mmui-accent-blue-rgb), 0.06);
}

.dashboard-page__power-action.is-active,
.dashboard-page__reinstall-family.is-active,
.dashboard-page__reinstall-option.is-active {
  border-color: color-mix(in srgb, var(--mmui-accent-blue) 72%, var(--mmui-shell-border));
  background: rgba(var(--mmui-accent-blue-rgb), 0.1);
  box-shadow: inset 0 0 0 1px rgba(var(--mmui-accent-blue-rgb), 0.12);
}

.dashboard-page__power-action.is-danger .dashboard-page__power-action-icon {
  color: var(--mmui-danger);
  background: color-mix(in srgb, var(--mmui-danger) 12%, transparent);
}

.dashboard-page__power-action strong,
.dashboard-page__reinstall-family strong,
.dashboard-page__reinstall-option strong {
  display: block;
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-body);
  font-weight: var(--mmui-text-body-emphasis-weight);
  line-height: var(--mmui-line-height-body);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-page__power-action small,
.dashboard-page__reinstall-family small,
.dashboard-page__reinstall-option small {
  display: block;
  min-width: 0;
  margin-top: 3px;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-page__reinstall-picker {
  display: grid;
  grid-template-columns: minmax(190px, 0.72fr) minmax(0, 1.28fr);
  gap: 0;
  min-width: 0;
}

.dashboard-page__reinstall-family-list,
.dashboard-page__reinstall-option-list {
  display: grid;
  align-content: start;
  gap: 8px;
  min-width: 0;
}

.dashboard-page__reinstall-family-list {
  padding-right: 14px;
}

.dashboard-page__reinstall-family,
.dashboard-page__reinstall-option {
  grid-template-columns: auto minmax(0, 1fr);
  gap: 10px;
  min-height: 62px;
  padding: 10px;
}

.dashboard-page__reinstall-image-panel {
  display: grid;
  align-content: start;
  gap: 10px;
  min-width: 0;
  padding: 12px 12px 12px 18px;
  border-left: 1px solid var(--mmui-shell-border);
}

.dashboard-page__reinstall-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
}

.dashboard-page__reinstall-option {
  grid-template-columns: minmax(0, 1fr) auto;
}

.dashboard-page__reinstall-option .anticon {
  color: var(--mmui-accent-blue);
  font-size: var(--mmui-font-size-body);
}

.dashboard-page__reinstall-confirm {
  display: grid;
  gap: 12px;
}

.dashboard-page__confirm-row {
  display: grid;
  gap: 8px;
  min-width: 0;
}

.dashboard-page__confirm-help {
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-line-height-caption);
}

.dashboard-page__modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.dashboard-page__remote-card {
  padding: 0;
  overflow: hidden;
}

.dashboard-page__remote-card :deep(.ant-card-body) {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

.dashboard-page__remote-more {
  padding-inline: 0 !important;
}

.dashboard-page__remote-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0;
  align-items: stretch;
  flex: 1 1 auto;
  min-height: 0;
  padding: var(--dashboard-card-padding);
}

.dashboard-page__remote-grid.is-count-1 {
  grid-template-columns: minmax(0, 1fr);
}

.dashboard-page__remote-grid.is-count-2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.dashboard-page__remote-grid.is-count-3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.dashboard-page__remote-item {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  gap: var(--mmui-space-2);
  min-width: 0;
  min-height: 0;
  height: 100%;
  padding: var(--dashboard-card-padding);
}

.dashboard-page__remote-item + .dashboard-page__remote-item {
  border-left: 1px solid var(--mmui-shell-border);
}

.dashboard-page__remote-item-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mmui-space-1);
}

.dashboard-page__remote-item-title {
  display: inline-flex;
  align-items: center;
  gap: var(--mmui-space-1);
  min-width: 0;
  color: var(--mmui-card-title);
  font-size: var(--mmui-font-size-title);
  font-weight: var(--mmui-font-weight-semibold);
}

.dashboard-page__remote-item-title img {
  display: block;
  width: 34px;
  min-width: 34px;
  height: 34px;
  object-fit: contain;
  line-height: 1;
}

.dashboard-page__remote-item-title.is-web img {
  filter: drop-shadow(0 8px 14px rgba(var(--mmui-accent-blue-rgb), 0.16));
}

.dashboard-page__remote-item-title.is-rdp img,
.dashboard-page__remote-item-title.is-ssh img {
  filter: drop-shadow(0 8px 14px rgba(37, 211, 145, 0.16));
}

.dashboard-page__remote-item-title.is-vnc img {
  filter: drop-shadow(0 8px 14px rgba(143, 99, 255, 0.16));
}

.dashboard-page__remote-item-state {
  flex: 0 0 auto;
  min-height: var(--mmui-space-3);
  padding: 0 var(--mmui-space-1);
  color: #53d769;
  font-size: var(--mmui-font-size-caption);
  line-height: var(--mmui-space-3);
  border-radius: 999px;
  background: rgba(83, 215, 105, 0.12);
}

.dashboard-page__remote-item-state.is-primary {
  color: var(--mmui-accent-blue);
  background: rgba(var(--mmui-accent-blue-rgb), 0.12);
}

.dashboard-page__remote-item-desc {
  min-height: 0;
  color: var(--mmui-text-soft);
  font-size: var(--mmui-font-size-body);
  line-height: var(--mmui-line-height-relaxed);
}

.dashboard-page__remote-item-btn {
  align-self: end;
  width: 100%;
  min-height: var(--mmui-control-height);
  margin-top: var(--mmui-space-2);
  border-radius: 8px;
}

.dashboard-page__overview,
.dashboard-page__status-card,
.dashboard-page__remote-card,
.dashboard-page__account-card,
.dashboard-page__network-card,
.dashboard-page__feature-card,
.dashboard-page__quick-card {
  border-radius: 8px !important;
}

@media (max-width: 1279px) {
  .dashboard-page__topbar-main {
    grid-template-columns: minmax(0, 1fr) max-content;
    gap: var(--mmui-space-2);
  }

  .dashboard-page__topbar-refresh-btn {
    display: none !important;
  }

  .dashboard-page__hero {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      'overview'
      'status'
      'remote'
      'account';
  }

  .dashboard-page__lower-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .dashboard-page__remote-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dashboard-page__remote-grid.is-count-1 {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 1023px) {
  .dashboard-page__overview-main {
    grid-template-columns: minmax(0, 1fr);
  }

  .dashboard-page__status-list {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .dashboard-page__visual {
    display: none;
  }

}

@media (max-width: 1180px) {
  .dashboard-page__topbar-action-btn--power,
  .dashboard-page__topbar-action-btn--share {
    display: none !important;
  }

  .dashboard-page__more-btn {
    width: 44px;
    min-width: 44px !important;
    padding-inline: 0 !important;
  }

  .dashboard-page__more-btn > span:not(.anticon),
  .dashboard-page__more-chevron {
    display: none !important;
  }
}

@media (max-width: 640px) {
  .dashboard-page {
    gap: var(--mmui-space-2);
  }

  .dashboard-page__hero {
    grid-template-areas:
      'overview'
      'status'
      'account'
      'remote';
  }

  .dashboard-page__hero,
  .dashboard-page__lower-grid {
    gap: var(--mmui-space-2);
  }

  .dashboard-page__overview-head,
  .dashboard-page__card-head {
    padding: 0 var(--dashboard-card-padding);
  }

  .dashboard-page__topbar-main {
    grid-template-columns: minmax(0, 1fr) max-content;
    gap: var(--mmui-space-1);
    /* Keep mobile topbar nearly flush too. Do not increase the 4px side padding. */
    padding: 14px 4px;
  }

  .dashboard-page__topbar-info {
    grid-template-columns: 46px minmax(0, 1fr);
  }

  .dashboard-page__topbar-icon {
    width: 46px;
    height: 46px;
    font-size: 24px;
  }

  .dashboard-page__topbar-name {
    font-size: var(--mmui-font-size-section);
  }

  .dashboard-page__topbar-meta {
    flex-wrap: nowrap;
  }

  .dashboard-page__topbar-location,
  .dashboard-page__topbar-separator {
    display: none;
  }

  .dashboard-page__topbar-ip {
    flex: 0 1 auto;
  }

  .dashboard-page__remote-connect-btn,
  .dashboard-page__topbar-action-btn,
  .dashboard-page__topbar-refresh-btn,
  .dashboard-page__more-btn {
    min-width: 0;
  }

  .dashboard-page__remote-connect-btn {
    width: 44px;
    min-width: 44px !important;
    padding-inline: 0 !important;
  }

  .dashboard-page__remote-connect-btn > span:not(.anticon) {
    display: none !important;
  }

  .dashboard-page__overview-main,
  .dashboard-page__status-list,
  .dashboard-page__account-list,
  .dashboard-page__quick-content,
  .dashboard-page__network-content,
  .dashboard-page__feature-slide {
    padding-left: var(--dashboard-card-padding);
    padding-right: var(--dashboard-card-padding);
  }

  .dashboard-page__lower-grid,
  .dashboard-page__network-content {
    grid-template-columns: minmax(0, 1fr);
  }

  .dashboard-page__network-content {
    display: grid;
  }

  .dashboard-page__network-primary {
    padding-right: 0;
    padding-bottom: var(--dashboard-card-padding);
  }

  .dashboard-page__network-metric {
    padding-left: 0;
    padding-right: 0;
    padding-top: var(--dashboard-card-padding);
  }

  .dashboard-page__network-metric--bandwidth {
    padding-bottom: var(--dashboard-card-padding);
  }

  .dashboard-page__network-item + .dashboard-page__network-item {
    border-left: 0;
    border-top: 1px solid var(--mmui-shell-border);
  }

  .dashboard-page__remote-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .dashboard-page__remote-item {
    padding: var(--dashboard-card-padding);
  }

  .dashboard-page__feature-slide {
    min-height: 132px;
    padding: var(--dashboard-card-padding) 42px 38px;
  }

  .dashboard-page__feature-arrow {
    width: 30px !important;
    min-width: 30px !important;
    height: 42px !important;
  }

  .dashboard-page__feature-arrow :deep(.anticon) {
    font-size: 26px;
  }

  .dashboard-page__feature-arrow--prev {
    left: 8px;
  }

  .dashboard-page__feature-arrow--next {
    right: 8px;
  }

  .dashboard-page__feature-dots {
    left: 42px;
    right: 42px;
    bottom: 12px;
    gap: 7px;
  }

  .dashboard-page__feature-card .dashboard-page__quick-media {
    min-width: 84px;
    flex-basis: 84px;
  }

  .dashboard-page__feature-card .dashboard-page__quick-graphic {
    width: 84px;
    height: 84px;
  }

  .dashboard-page__feature-card .dashboard-page__quick-icon {
    font-size: 58px;
  }

  .dashboard-page__feature-card .dashboard-page__quick-asset {
    width: 78px;
    height: 78px;
  }

  .dashboard-page__status-item.is-circle {
    grid-template-columns: minmax(0, 1fr);
    justify-items: center;
    text-align: center;
    padding-inline: 0;
  }

  .dashboard-page__status-list {
    --dashboard-status-ring-size: clamp(72px, 25vw, 96px);
    grid-template-columns: repeat(3, minmax(0, 1fr));
    column-gap: 2px;
    padding-left: 8px;
    padding-right: 8px;
  }

  .dashboard-page__status-circle-value {
    font-size: clamp(16px, 4.6vw, 20px);
  }

  .dashboard-page__status-circle-title {
    font-size: 12px;
  }

  .dashboard-page__status-circle-unit,
  .dashboard-page__status-circle-meta small {
    font-size: 11px;
  }

  .dashboard-page__status-mini-wave {
    --wave-height: 14px;
    gap: 2px;
    width: min(56px, 18vw);
  }

  .dashboard-page__status-mini-wave span {
    width: 2px;
  }

  .dashboard-page__status-circle-meta {
    justify-items: center;
  }

  .dashboard-page__reinstall-picker {
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
  }

  .dashboard-page__reinstall-family-list {
    padding-right: 0;
  }

  .dashboard-page__reinstall-image-panel {
    padding: 12px;
    border-left: 0;
    border-top: 1px solid var(--mmui-shell-border);
  }

}

@media (prefers-reduced-motion: reduce) {
  .mmui-page-tab-enter-active,
  .mmui-page-tab-leave-active {
    transition: opacity 0.12s ease;
  }

  .mmui-page-tab-enter-from,
  .mmui-page-tab-leave-to {
    transform: none;
  }

  .dashboard-page.mmui-page-tab-enter-active .home-card,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__topbar-icon,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__topbar-copy,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__topbar-actions,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-row,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__account-row,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__server-art,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__visual-screen::after,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-circle-arc,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-circle-value,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__status-mini-wave span,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__remote-item,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__network-item,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__network-bar i,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__quick-copy,
  .dashboard-page.mmui-page-tab-enter-active .dashboard-page__quick-graphic,
  .mmui-subpage.mmui-page-tab-enter-active :deep(.home-card),
  .mmui-subpage.mmui-page-tab-enter-active :deep(.ant-card),
  .mmui-subpage.mmui-page-tab-enter-active :deep(.resource-page__table-panel),
  .mmui-subpage.mmui-page-tab-enter-active :deep(.remote-page__panel),
  .mmui-subpage.mmui-page-tab-enter-active :deep(.system-page__card) {
    animation: none;
  }
}
</style>


import { mmuiThemeModes } from '@/theme/antdTheme';

export function getMmuiCssVars(mode) {
  const current = mmuiThemeModes[mode]?.antdTheme ?? mmuiThemeModes.light.antdTheme;
  const token = current.token;
  const components = current.components ?? {};
  const isDark = mode === 'dark';

  return {
    '--mmui-page-gradient': isDark
      ? 'linear-gradient(#141414, #000000 28%)'
      : 'radial-gradient(circle at 18% 0%, rgba(68, 96, 255, 0.16) 0%, rgba(68, 96, 255, 0) 19%), radial-gradient(circle at 84% 3%, rgba(118, 136, 255, 0.13) 0%, rgba(118, 136, 255, 0) 17%), linear-gradient(180deg, #f7f8ff 0%, #fbfcff 22%, #ffffff 58%, #fbfcff 100%)',
    '--mmui-header-bg': components.Layout?.headerBg ?? token.colorBgElevated,
    '--mmui-shell-border': token.colorBorderSecondary,
    '--mmui-sidebar-text': components.Menu?.itemColor ?? token.colorTextSecondary,
    '--mmui-sidebar-icon': isDark ? token.colorTextSecondary : token.colorText,
    '--mmui-sidebar-hover': components.Menu?.itemHoverBg ?? token.colorFillSecondary,
    '--mmui-sidebar-active': components.Menu?.itemSelectedBg ?? token.colorFillSecondary,
    '--mmui-sidebar-active-text': components.Menu?.itemSelectedColor ?? token.colorText,
    '--mmui-scrollbar-track': isDark ? '#141414' : 'transparent',
    '--mmui-scrollbar-thumb': isDark ? '#000000' : 'rgba(0, 0, 0, 0.22)',
    '--mmui-heading': isDark ? 'rgba(255, 255, 255, 0.88)' : '#111827',
    '--mmui-text': token.colorText,
    '--mmui-text-soft': isDark ? 'rgba(255, 255, 255, 0.64)' : 'rgba(17, 24, 39, 0.64)',
    '--mmui-text-muted': isDark ? 'rgba(255, 255, 255, 0.46)' : 'rgba(17, 24, 39, 0.48)',
    '--mmui-divider': isDark ? '#1a1a1a' : '#f0f0f0',
    '--mmui-card-surface': token.colorBgContainer,
    '--mmui-card-surface-elevated': isDark
      ? 'radial-gradient(circle, rgba(20, 20, 20, 1) 47%, rgb(20 20 28) 100%)'
      : '#ffffff',
    '--mmui-card-border': token.colorBorderSecondary,
    '--mmui-card-shadow': token.boxShadowTertiary,
    '--mmui-card-title': isDark ? 'rgba(255, 255, 255, 0.88)' : '#111827',
    '--mmui-card-subtitle': token.colorTextSecondary,
    '--mmui-card-hero-text': '#ffffff',
    '--mmui-card-hero-subtext': isDark ? 'rgba(255, 255, 255, 0.85)' : 'rgba(255, 255, 255, 0.94)',
    '--mmui-accent-blue': token.colorPrimary,
    '--mmui-accent-blue-rgb': '68, 96, 255',
    '--mmui-accent-blue-soft': isDark ? 'rgba(68, 96, 255, 0.42)' : 'rgba(68, 96, 255, 0.32)',
    '--mmui-accent-green': '#4caf50',
    '--mmui-monitor-unit': isDark ? token.colorTextTertiary : '#9e9e9ec2',
    '--mmui-screen-overlay': isDark ? 'rgba(0, 0, 0, 0.2)' : 'linear-gradient(to bottom, rgba(68, 96, 255, 0.08), rgba(255, 255, 255, 0))',
    '--mmui-screen-surface': isDark ? 'linear-gradient(135deg, #2c2f3e, #3a3f4d)' : 'linear-gradient(180deg, #fafafa 0%, #f4f4f4 100%)',
    '--mmui-screen-line': isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(68, 96, 255, 0.1)',
    '--mmui-screen-line-opacity': isDark ? '0.6' : '0.5',
    '--mmui-power-ring-bg': isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(68, 96, 255, 0.1)',
    '--mmui-power-ring-border': token.colorPrimary,
    '--mmui-power-ring-color': isDark ? 'rgb(255 255 255 / 65%)' : token.colorPrimary,
    '--mmui-card-action-bg': components.Button?.defaultBg ?? token.colorBgContainer,
    '--mmui-card-action-text': components.Button?.defaultColor ?? token.colorText,
    '--mmui-card-action-border': components.Button?.defaultBorderColor ?? '#d9d9d9',
    '--mmui-mobile-drawer-bg': token.colorBgElevated,
  };
}

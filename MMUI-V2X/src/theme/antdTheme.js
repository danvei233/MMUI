import { theme } from 'ant-design-vue';

const fontFamily =
  '-apple-system,BlinkMacSystemFont,"SF Pro Text","SF Pro Display","SF Compact Text","SF Compact Display","PingFang SC","Hiragino Sans GB","Microsoft YaHei UI","Microsoft YaHei","Helvetica Neue",Arial,sans-serif';

const primaryColor = '#4460ff';

const sharedToken = {
  colorPrimary: primaryColor,
  colorInfo: primaryColor,
  colorSuccess: '#5fb878',
  colorWarning: '#ffc107',
  colorError: '#ff5722',
  borderRadius: 6,
  borderRadiusLG: 6,
  borderRadiusSM: 6,
  fontFamily,
  fontSize: 14,
  fontSizeLG: 17,
  lineHeight: 19 / 14,
  lineHeightLG: 22 / 17,
  fontWeightStrong: 600,
  motionDurationFast: '0.18s',
  motionDurationMid: '0.24s',
  controlHeight: 40,
  controlHeightLG: 40,
  controlHeightSM: 32,
  padding: 16,
  paddingSM: 8,
  paddingXS: 8,
  marginXS: 8,
  marginSM: 8,
  margin: 16,
};

const sharedComponents = {
  Layout: {
    headerHeight: 56,
    headerPadding: '0 16px',
    siderBg: 'transparent',
    bodyBg: 'transparent',
    triggerBg: 'transparent',
  },
  Card: {
    borderRadiusLG: 6,
    headerBg: 'transparent',
    lineWidth: 0,
    bodyPadding: 16,
    bodyPaddingSM: 16,
  },
  Button: {
    borderRadius: 8,
    borderRadiusSM: 8,
    borderRadiusLG: 8,
    primaryShadow: 'none',
    defaultShadow: 'none',
  },
  Collapse: {
    headerBg: 'transparent',
    contentBg: 'transparent',
    borderRadiusLG: 8,
    colorBorder: 'transparent',
  },
  Spin: {
    dotSizeLG: 30,
  },
};

export const mmuiThemeModes = {
  light: {
    antdTheme: {
      algorithm: theme.defaultAlgorithm,
      token: {
        ...sharedToken,
        colorText: 'rgba(0, 0, 0, 0.88)',
        colorTextSecondary: 'rgba(0, 0, 0, 0.65)',
        colorTextTertiary: 'rgba(0, 0, 0, 0.45)',
        colorBorderSecondary: 'rgba(15, 23, 42, 0.09)',
        colorFillSecondary: 'rgba(0, 0, 0, 0.04)',
        colorBgContainer: '#ffffff',
        colorBgElevated: '#ffffff',
        boxShadowTertiary:
          '0 1px 2px 0 rgba(0, 0, 0, 0.03), 0 1px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px 0 rgba(0, 0, 0, 0.02)',
      },
      components: {
        ...sharedComponents,
        Layout: {
          ...sharedComponents.Layout,
          headerBg: 'rgba(255, 255, 255, 0.6)',
        },
        Card: {
          ...sharedComponents.Card,
          colorBorderSecondary: 'rgba(15, 23, 42, 0.09)',
          boxShadowTertiary:
            '0 1px 2px 0 rgba(0, 0, 0, 0.03), 0 1px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px 0 rgba(0, 0, 0, 0.02)',
        },
        Button: {
          ...sharedComponents.Button,
          textHoverBg: 'rgba(0, 0, 0, 0.04)',
          textTextHoverColor: 'rgba(0, 0, 0, 0.88)',
          defaultHoverBorderColor: 'rgba(5, 5, 5, 0.12)',
          defaultHoverColor: 'rgba(0, 0, 0, 0.88)',
        },
        Menu: {
          itemBg: 'transparent',
          subMenuItemBg: 'transparent',
          itemSelectedBg: 'rgba(0, 0, 0, 0.04)',
          itemHoverBg: 'rgba(0, 0, 0, 0.04)',
          itemHeight: 40,
          itemMarginInline: 8,
          itemBorderRadius: 8,
          itemColor: 'rgba(0, 0, 0, 0.88)',
          itemSelectedColor: 'rgba(0, 0, 0, 0.88)',
          itemHoverColor: 'rgba(0, 0, 0, 0.88)',
          iconSize: 18,
        },
      },
    },
  },
  dark: {
    antdTheme: {
      algorithm: theme.darkAlgorithm,
      token: {
        ...sharedToken,
        colorText: 'rgba(255, 255, 255, 0.85)',
        colorTextSecondary: 'rgba(255, 255, 255, 0.65)',
        colorTextTertiary: 'rgba(255, 255, 255, 0.45)',
        colorBorderSecondary: 'rgba(255, 255, 255, 0.16)',
        colorFillSecondary: 'rgba(255, 255, 255, 0.08)',
        colorBgContainer: '#141414',
        colorBgElevated: '#141414',
        colorBgLayout: '#141414',
        boxShadowTertiary:
          '0 1px 2px 0 rgba(0, 0, 0, 0.22), 0 8px 20px -6px rgba(0, 0, 0, 0.28)',
      },
      components: {
        ...sharedComponents,
        Layout: {
          ...sharedComponents.Layout,
          headerBg: 'rgba(31, 31, 31, 0.6)',
        },
        Card: {
          ...sharedComponents.Card,
          colorBorderSecondary: 'rgba(255, 255, 255, 0.16)',
          boxShadowTertiary:
            '0 1px 2px 0 rgba(0, 0, 0, 0.22), 0 8px 20px -6px rgba(0, 0, 0, 0.28)',
        },
        Button: {
          ...sharedComponents.Button,
          colorText: 'rgba(255, 255, 255, 0.85)',
          textHoverBg: 'rgba(255, 255, 255, 0.08)',
          textTextHoverColor: '#ffffff',
          defaultBg: '#141414',
          defaultColor: 'rgba(255, 255, 255, 0.85)',
          defaultBorderColor: '#d9d9d9',
          defaultHoverBorderColor: primaryColor,
          defaultHoverColor: '#ffffff',
        },
        Menu: {
          itemBg: 'transparent',
          subMenuItemBg: 'transparent',
          itemSelectedBg: 'linear-gradient(90deg, rgba(68, 96, 255, 1) 0%, rgba(110, 132, 255, 1) 100%)',
          itemHoverBg: 'rgba(255, 255, 255, 0.08)',
          itemHeight: 40,
          itemMarginInline: 8,
          itemBorderRadius: 8,
          itemColor: 'rgba(255, 255, 255, 0.65)',
          itemSelectedColor: '#ffffff',
          itemHoverColor: '#ffffff',
          iconSize: 18,
        },
      },
    },
  },
};

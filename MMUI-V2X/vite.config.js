import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

const devOrigin = (process.env.MMUI_DEV_ORIGIN || 'http://127.0.0.1:5173').replace(/\/$/, '');
const buildOutDir = process.env.MMUI_ECS_DIST_DIR || process.env.MMUI_OUT_DIR || '../qzsystem/public/src/static/mmui';

export default defineConfig(({ command }) => ({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  base: command === 'serve' ? '/' : '/src/static/mmui/',
  build: {
    outDir: path.resolve(__dirname, buildOutDir),
    assetsDir: 'assets',
    manifest: true,
    emptyOutDir: true,
    target: 'es2018',
    rollupOptions: {
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    origin: devOrigin,
  },
}));

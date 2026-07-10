import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';
import fs from 'node:fs/promises';

const devOrigin = (process.env.MMUI_DEV_ORIGIN || 'http://127.0.0.1:5173').replace(/\/$/, '');
const buildOutDir = process.env.MMUI_ECS_DIST_DIR || process.env.MMUI_OUT_DIR || '../qzsystem/public/src/static/mmui';

function preserveUploadsPlugin() {
  let outDir = '';
  return {
    name: 'mmui-preserve-uploads',
    apply: 'build',
    configResolved(config) {
      outDir = config.build.outDir;
    },
    async buildStart() {
      await fs.mkdir(outDir, { recursive: true });
      const entries = await fs.readdir(outDir, { withFileTypes: true });
      await Promise.all(entries
        .filter((entry) => entry.name !== 'uploads')
        .map((entry) => fs.rm(path.join(outDir, entry.name), { recursive: true, force: true })));
    },
  };
}

export default defineConfig(({ command }) => ({
  root: __dirname,
  plugins: [vue(), preserveUploadsPlugin()],
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
    emptyOutDir: false,
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

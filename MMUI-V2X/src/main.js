import { createApp } from 'vue';
import Antd from 'ant-design-vue';
import App from './App.vue';
import { pinia } from './stores/pinia';
import 'ant-design-vue/dist/reset.css';
import './styles/global.css';

const app = createApp(App);

app.use(pinia);
app.use(Antd);
app.mount('#app');

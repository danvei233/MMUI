<script setup lang="ts">
import {onMounted, reactive, ref} from 'vue';
import { UserOutlined, LockOutlined } from '@ant-design/icons-vue';
import {auroraBoatTheme} from "@/assets/theme.ts";
import { h } from 'vue'
import { message } from 'ant-design-vue';
import { notification, Button } from 'ant-design-vue'

import router from '../router/index.ts';

interface FormState {
  type:string;
  username: string;
  password: string;
}
interface Res {
  code:number;
  msg:string;
  data:string;
  url:string;
  wait:number;
}


interface loginType{
  code:number;
  name:string;
  user:string;
  passwd:string;
}



const BASEURL = window.location.origin ;

const formState = reactive<FormState>({
  type : "1",
  username: '',
  password: '',

});
const disabledLogin=ref(false);
const getTopCenterContainer = () =>{
  let el = document.getElementById('notif-top-center')
  if (!el) {
    el = document.createElement('div')
    el.id = 'notif-top-center'
    Object.assign(el.style, {
      position: 'fixed',
      top: '16px',
      left: 0,
      right: 0,
      display: 'flex',
      justifyContent: 'center',
      pointerEvents: 'none',    // 让空白区域不拦截鼠标
      zIndex: 1010,
    })
    document.body.appendChild(el)
  }
  return el
}
const openClipboardBar = (onInsert?: () => void) => {
  const key = `clip-${Date.now()}`
  notification.open({
    // message: '提示',
    message: null, // 只保留描述更像“条形提示”
    description: '检测到剪贴板疑似【面板账号/密码】信息，是否自动填入？',
    // icon: () => h(InfoCircleOutlined),
    duration: 0,            // 不自动关闭
    placement: 'top',       // 先放 top，再通过自定义容器居中
    key,
    btn: () =>
        h('div',
            { style: 'pointer-events:auto; display:flex; gap:8px;' },
            [
              h(
                  Button,
                  {
                    type: 'primary',
                    size: 'small',
                    onClick: () => {
                      onInsert?.()
                      notification.close(key)
                    },
                  },
                  { default: () => '插入' },
              ),
              h(
                  Button,
                  {
                    size: 'small',
                    onClick: () => notification.close(key),
                  },
                  { default: () => '忽略' },
              ),
            ],
        ),
    getContainer: getTopCenterContainer, // 顶部居中容器
    onClose: () => {
      // 这里写关闭后的逻辑
      console.log('notification closed')
    },
  })
}
const extractPanelInfo=(text:string)=> {
  const accountMatch = text.match(/面板：.*?\s+账号：(\S+)\s+密码：(\S+)/);

  if (accountMatch) {
    return {
      account: accountMatch[1] || "",
      password: accountMatch[2] || ""
    };
  }

  return {
    account: "",
    password: ""
  };
}
async function Login(values:any,cfg:loginType):Promise<Res> {
  let body:string;
  if (cfg.code==0){
     body = `${cfg.user}=${values.username}&${cfg.passwd}=${values.password}`
  }else {
    body = `type=${cfg.code}&${cfg.user}=${values.username}&${cfg.passwd}=${values.password}`
  }


  let res = await fetch(BASEURL + `/index.php/control/`+cfg.name+`/login`, {
    method: 'POST',
    headers: {
      "x-requested-with":"XMLHttpRequest",
      "content-type":"application/x-www-form-urlencoded; charset=UTF-8"
    },
    body:body,
  })
  if (!res.ok) {

    const errorText = await res.text();
    return {
      code:0,
      msg: res.statusText+ errorText,
      data:"",
      url:"",
      wait:3,
    }
  }
return res.json();
}
const fixLogin=(values:any):Promise<Res>=>{
  switch(values.type) {
    case '1':
      return Login(values,{
        code:1,
        name:'ecs',
        user:'host_name',
        passwd:'panel_password'
      })
    case '2':
      //vhost
      return Login(values,{
        code:0,
        name:'vhost',
        user:'site_name',
        passwd:'ftp_passwd'
      })
    case '3':
      //mysql
      return Login(values,{
        code:0,
        name:'vhost',
        user:'dataname',
        passwd:'datapasswd'
      })
    case '4':
      //mssql
      return Login(values,{
        code:0,
        name:'vhost',
        user:'dataname',
        passwd:'datapasswd'
      })
    case '5':
      return Login(values,{
        code:5,
        name:'baremeta',
        user:'host_name',
        passwd:'panel_password'
      })}
  return (Promise<Res>).resolve({
    code:0,
    msg:"异常的实例类型!",
    url:"",
    data:"",
    wait:3,
  })
}
const onFinish = (values: any) => {
  disabledLogin.value = true;
  fixLogin(values).then(res=>{
    res.code==1?message.success('登录成功！'):message.error(res.msg);
    if (res.code!=0){
      // router.push({ path: res.url });
      location.href = BASEURL + res.url;
    }
  });
  disabledLogin.value = false;
};
const onFinishFailed = (errorInfo: any) => {
  console.log('Failed:', errorInfo);
};

onMounted(async()=>{
  try{
    const text = await navigator.clipboard.readText();
    const info = extractPanelInfo(text);
    if (info.account&& info.password) {
      openClipboardBar(()=>{
        formState.type = '1';
        formState.username = info.account;
        formState.password = info.password;

      })
    }

  }catch(e){
    console.error('无法读取剪贴板内容:', e);
  }

})
</script>

<template>
  <a-config-provider
      :theme="auroraBoatTheme"
  />

  <div >
    <a-card :bordered="false" style="
    background-color: rgba(255,255,255,0.80);
    backdrop-filter: blur(14px);
    width: 400px;
    border-radius: 12px;
    padding: 20px 24px 20px 20px;
">

      <div class="card-top">




        <div class="card-logo">

          <div class="logo">
            <img class="logo" src="../assets/logo.webp" alt="logo">
          </div>
<!--          <h1>AuroraBoat</h1>-->


        </div>
        <h2>欢迎回来~</h2>
        <h3>AuroraBoat Login</h3>


      </div>








      <a-form
          :model="formState"
          name="basic"

          :label-col="{ span: 6 }"
          :wrapper-col="{ span: 18 }"
          autocomplete="off"
          :required-mark="false"
          @finish="onFinish"
          @finishFailed="onFinishFailed"
      >





        <a-form-item
            controlHeight="36px"
            name="type"
            label="实例类型"
            has-feedback
            style="display: flex;
  align-items: center;  "
            :rules="[{ required: true, message: '请选择实例类型!' }]"
        >
          <a-select v-model:value="formState.type" placeholder="请选择实例类型">
            <a-select-option value="1">云主机</a-select-option>
            <a-select-option value="2">虚拟主机</a-select-option>
            <a-select-option value="3">MYSQL</a-select-option>
            <a-select-option value="4">MSSQL</a-select-option>
            <a-select-option value="5">物理机/托管</a-select-option>
          </a-select>
        </a-form-item>





        <a-form-item
            class = “form-item”
            label="实例名称"
            name="username"
            style="display: flex;
  align-items: center;  "
            :rules="[{ required: true, message: '请输入实例名称!' }]"
        >
          <a-input v-model:value="formState.username" >
            <template #prefix>
              <UserOutlined class="site-form-item-icon" />
            </template>
          </a-input>
        </a-form-item>




        <a-form-item
            class = “form-item”
            label="登入密码"
            name="password"
            style="display: flex;
  align-items: center;  "
            :rules="[{ required: true, message: '请输入登入密码!' }]"
        >
          <a-input-password v-model:value="formState.password" >
            <template #prefix>
              <LockOutlined class="site-form-item-icon" />
            </template>

          </a-input-password>
        </a-form-item>



        <a-form-item    :wrapper-col="{ offset: 0, span: 24 }">
          <a-button :disabled="disabledLogin" type="primary" html-type="submit" class="login-button"  >
            登录
          </a-button>
        </a-form-item>



      </a-form>


    </a-card>
  </div>



</template>

<style scoped>

.logo{
  width: 60px;
  height: 60px;
}
.card-logo h1{
color: #6968fd;
}
.card-top h2{
  font-size: 32px;
  font-weight: bold;
  color: #6968fd;
}
.card-top h3{
  color: #9594ff;
}
.card-top{
  padding-left: 8px;
  padding-bottom: 32px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: start;


}

.card-logo{
  display: flex;
  flex-direction: row;
  gap: 16px;

  align-items: center;
  justify-content: center;
}
.login-button {
  width: 100%;
}

.ant-form-item {
  margin-bottom: 24px !important;
}

.ant-form-item-label {
  display: flex;
  align-items: center;  /* 垂直居中 */
}
</style>

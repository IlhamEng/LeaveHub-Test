import { createApp } from "vue";
import { createPinia } from "pinia";

import App from "./App.vue";
import router from "./routes";

import "./style.css";

// ─── Create app ───────────────────────────────────────────────────────────────

const app = createApp(App);

// ─── Install plugins ──────────────────────────────────────────────────────────

// 1. Pinia (state management) — must be installed before the router
//    so that navigation guards can call useAuthStore()
app.use(createPinia());

// 2. Vue Router — guards use the auth store, so Pinia must come first
app.use(router);

// ─── Mount ────────────────────────────────────────────────────────────────────

app.mount("#app");

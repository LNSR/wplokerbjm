import "@assets/css/tailwind.css";
import { container } from "@inversify/inversify/inversify.config";
import { ComponentMounter } from "./app";
import type { ComponentConfig } from "@/types";
import Header from "@/layouts/Header.vue";
import JobGrid from "@/components/Homepage/JobGrid.vue";
import JobCarousel from "@/components/Homepage/JobCarousel.vue";
import FloatingActionButton from "@/components/FloatingActionButton.vue";
import SingleLowongan from "@/pages/single-lowongan.vue";
import PasangIklanView from "@/pages/pasang-iklan-loker.vue";
import Footer from "@/layouts/Footer.vue";
import SearchForm from "@/components/Homepage/SearchForm.vue";

async function mountHomepageComponents() {
  const homepageConfigs: ComponentConfig[] = [
    { selector: "#header", component: Header },
    { selector: "#footer", component: Footer },
    { selector: "#search-form", component: SearchForm },
    { selector: "#job-grid", component: JobGrid },
    { selector: "#job-carousel", component: JobCarousel },
    { selector: "#floating-action-button", component: FloatingActionButton },
    { selector: "#single-lowongan", component: SingleLowongan },
    { selector: "#pasang-iklan-loker", component: PasangIklanView },
  ];
  container.get(ComponentMounter).mount(homepageConfigs);
}
await mountHomepageComponents();

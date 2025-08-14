import "@assets/css/tailwind.css";
import { container, MountApp } from "@/inversify.config.ts";

container.get<MountApp>("MountApp")
  .mountComponents()
  .catch((err): any => {
    console.error("MountApp.mountComponents() failed:", err);
  });
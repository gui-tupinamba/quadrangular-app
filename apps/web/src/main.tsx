import ReactDOM from "react-dom/client";
import App from "./App";
import { applyBranding } from "./branding";
import "./style.css";

applyBranding();
ReactDOM.createRoot(document.getElementById("root")!).render(<App />);

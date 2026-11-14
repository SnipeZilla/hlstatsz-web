window.addEventListener("DOMContentLoaded", () => {

// The night sky (easter.css): the stars that twinkle, then the hills and the glow over them, under the logo and the menu
const top = document.querySelector(".hlstats-top");
if(!top) return;

const sky = document.createElement("div");
sky.className = "easter-sky";
sky.setAttribute("aria-hidden", "true");
for(let i = 0; i < 3; i++) sky.appendChild(document.createElement("i"));
sky.appendChild(document.createElement("span"));
top.insertBefore(sky, top.firstChild);
top.classList.add("easter-has-sky");
});

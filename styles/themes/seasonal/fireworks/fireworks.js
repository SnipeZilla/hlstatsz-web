window.addEventListener("DOMContentLoaded", () => {

// The night sky (fireworks.css): the stars that twinkle, the fireworks, then the skyline over them, under the logo and
// the menu
const top = document.querySelector(".hlstats-top");
if(!top) return;

const sky = document.createElement("div");
sky.className = "fireworks-sky";
sky.setAttribute("aria-hidden", "true");
for(let i = 0; i < 4; i++) sky.appendChild(document.createElement("i"));
for(let i = 0; i < 5; i++) sky.appendChild(document.createElement("b"));
sky.appendChild(document.createElement("span"));
top.insertBefore(sky, top.firstChild);
top.classList.add("fireworks-has-sky");
});

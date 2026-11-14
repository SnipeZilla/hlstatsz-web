window.addEventListener("DOMContentLoaded", () => {

// Now and then a leaf falls through the header (thanksgiving.css), under the logo and the menu; none for visitors who
// ask for less motion
if(matchMedia("(prefers-reduced-motion: reduce)").matches) return;

const top = document.querySelector(".hlstats-top");
if(!top) return;

const fall = document.createElement("div");
fall.className = "thanksgiving-fall";
fall.setAttribute("aria-hidden", "true");

for(let i = 0; i < 6; i++){

    const leaf = document.createElement("i");
    leaf.className = "l" + (i % 3);
    leaf.style.left = (3 + i * 16 + Math.random() * 10).toFixed(1) + "%";
    leaf.style.animationDuration = (10 + Math.random() * 6).toFixed(1) + "s";
    leaf.style.animationDelay = (-Math.random() * 16).toFixed(1) + "s";

    const turn = document.createElement("b");
    turn.style.animationDuration = (2.2 + Math.random() * 1.4).toFixed(1) + "s";
    turn.style.animationDelay = (-Math.random() * 3).toFixed(1) + "s";

    leaf.appendChild(turn);
    fall.appendChild(leaf);
}

top.insertBefore(fall, top.firstChild);
});

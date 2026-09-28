window.addEventListener("DOMContentLoaded", () => {

    const still = matchMedia("(prefers-reduced-motion: reduce)").matches;

    // Fog along the bottom of the header: over the graves, under the logo and the menu
    const top = document.querySelector(".hlstats-top");
    if(top){

        const fog = document.createElement("div");
        fog.className = "halloween-fog";
        fog.setAttribute("aria-hidden", "true");
        fog.appendChild(document.createElement("i"));
        top.insertBefore(fog, top.firstChild);
    }

    // Now and then a bat flutters across the top of the page, and a ghost drifts by lower down
    if(still) return;

    const night = document.createElement("div");
    night.className = "halloween-night";
    night.setAttribute("aria-hidden", "true");

    function add(kind, y, seconds, delay){

        const el = document.createElement("i");
        el.className = "halloween-" + kind;
        el.style.top = y.toFixed(1) + "vh";
        el.style.animationDuration = seconds.toFixed(1) + "s";
        el.style.animationDelay = delay.toFixed(1) + "s";
        el.appendChild(document.createElement("b"));
        night.appendChild(el);
    }

    for(let i = 0; i < 3; i++){

        add("bat", 4 + Math.random() * 24, 26 + Math.random() * 14, 2 + i * 9 + Math.random() * 4);
    }
    for(let i = 0; i < 2; i++){

        add("ghost", 30 + Math.random() * 45, 70 + Math.random() * 30, 6 + i * 28 + Math.random() * 6);
    }

    document.body.appendChild(night);
});

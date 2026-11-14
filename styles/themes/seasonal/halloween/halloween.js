window.addEventListener("DOMContentLoaded", () => {

    const still = matchMedia("(prefers-reduced-motion: reduce)").matches;

    // The art in layers, in the order it is drawn (halloween-<name>-layers.svg, one row each): a still layer (null),
    // or the light of a pumpkin or a window, flickering from its delay (s). An animation inside an SVG background
    // painted the whole header again on every frame; a layer's opacity is moved by the graphics card
    const ART = {
        house:     [null, 0, null, -1.1, null, -2.2, null, -0.6, null, -0.7, null, -0.7, null],
        graveyard: [null, 0, null, 0, -1.3, null, -1.3, null],
        graves:    [null, -0.9, null, -0.9, null]
    };

    function layers(name){

        const art = document.createElement("div");
        art.className = "halloween-" + name;
        art.setAttribute("aria-hidden", "true");
        ART[name].forEach((delay, row) => {
            const layer = document.createElement("span");
            layer.style.setProperty("--row", row);
            if(delay !== null){
                layer.className = "halloween-flicker";
                layer.style.animationDelay = delay + "s";
            }
            art.appendChild(layer);
        });
        return art;
    }

    // Fog along the bottom of the header: over the graves, under the logo and the menu
    const top = document.querySelector(".hlstats-top");
    if(top){

        const fog = document.createElement("div");
        fog.className = "halloween-fog";
        fog.setAttribute("aria-hidden", "true");
        fog.appendChild(document.createElement("i"));
        top.insertBefore(fog, top.firstChild);

        // Under the fog: the stars that twinkle, the moon, the house and the graveyard (halloween.css)
        const sky = document.createElement("div");
        sky.className = "halloween-sky";
        sky.setAttribute("aria-hidden", "true");
        sky.appendChild(document.createElement("i"));
        sky.appendChild(document.createElement("i"));
        sky.appendChild(document.createElement("b"));
        sky.appendChild(layers("house"));
        sky.appendChild(layers("graveyard"));
        top.insertBefore(sky, top.firstChild);
        top.classList.add("halloween-has-sky");
    }

    // The graves along the top of the footer, in layers too
    const footer = document.querySelector(".hlstats-footer");
    if(footer){

        footer.appendChild(layers("graves"));
        footer.classList.add("halloween-has-graves");
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

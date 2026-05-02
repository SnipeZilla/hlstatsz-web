window.addEventListener("DOMContentLoaded", () => {
  const parents = document.querySelectorAll(".hlstats-footer, .hlstats-top");
  if (!parents.length) return;

  const chars = "私は誰ですか？ああ、それは素晴らしいパズルです。1!2@3#4$5%6^7&8*9(0)".split("");
  const font_size = 10;

  parents.forEach((parentDiv) => {
    if (getComputedStyle(parentDiv).position === "static") {
      parentDiv.style.position = "relative";
    }

    const canvas = document.createElement("canvas");
    canvas.className = "matrix-canvas";
    parentDiv.appendChild(canvas);

    const ctx = canvas.getContext("2d");

    function resize() {
      canvas.width = parentDiv.clientWidth || window.innerWidth;
      canvas.height = parentDiv.clientHeight || 104; // keep your original height (or parentDiv.clientHeight)

      const columns = Math.floor(canvas.width / font_size);
      drops.length = 0;
      for (let x = 0; x < columns; x++) drops[x] = 1;
    }

    let drops = [];
    resize();

    function draw() {
      ctx.fillStyle = "rgba(0, 0, 0, 0.05)";
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      ctx.fillStyle = "#0F0";
      ctx.font = font_size + "px arial";

      for (let a = 0; a < drops.length; a++) {
        const b = chars[(Math.random() * chars.length) | 0];
        ctx.fillText(b, a * font_size, drops[a] * font_size);

        if (drops[a] * font_size > canvas.height && Math.random() > 0.975) {
          drops[a] = 0;
        }
        drops[a]++;
      }
    }

    setInterval(draw, 33);
    window.addEventListener("resize", resize);
  });
});

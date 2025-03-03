function includeHTML() {
var elements = document.querySelectorAll("[data-include-html]");
elements.forEach(function(el) {
    var file = el.getAttribute("data-include-html");
    fetch(file)
    .then(response => {
        if (response.ok) return response.text();
        throw new Error('Network response was not ok.');
    })
    .then(data => {
        el.innerHTML = data;
        el.removeAttribute("data-include-html");

        includeHTML();
    })
    .catch(error => {
        console.log('Error fetching the file:', error);
        el.innerHTML = "Page not found.";
    });
});
}






document.addEventListener("DOMContentLoaded", async function () {
try {
    // Obtém a URL atual
    const urlAtual = window.location.href.toLowerCase();

    
    
    // Carrega as categorias
    const categoriasResponse = await fetch("categorias.json");
    const categorias = await categoriasResponse.json();
    
    let categoriaEncontrada = null;
    
    // Verifica se a URL contém alguma das categorias
    for (let categoriaObj of categorias) {
        let categoria = categoriaObj.category.toLowerCase();
        if (urlAtual.includes(categoria)) {
            categoriaEncontrada = categoria;
            break;
        }
    }
    
    if (!categoriaEncontrada) {
        console.warn("Nenhuma categoria correspondente encontrada.");
        return;
    }
    
    // Carrega o JSON da categoria correspondente
    const produtosResponse = await fetch(`${categoriaEncontrada}.json`);
    const produtos = await produtosResponse.json();
    
    const container = document.getElementById("product-container");
    
    // Limpa o container antes de inserir novos produtos
    container.innerHTML = "";


// Insere os produtos na página
for (let produto of produtos) {
    let logo = produto.logo;
    if (produto.logo.toLowerCase() === "amazon") {
        logo = "img/Amazon-Logo.png";
    } else if (produto.logo.toLowerCase() === "shopee") {
        logo = "img/shopee-logo.png";
    }

    const card = document.createElement("div");
    card.className = "col";
    card.innerHTML = `
        <div class="card h-100 border-0 rounded-pill text-center">
            <a class="img-prod" href="${produto.url}" target="_blank">
                <img src="${produto.image}" class="card-img-top" alt="${produto.name}">
            </a>
            <img class="logo-brand" src="${logo}" alt="logo da marca">
            <div class="card-body">
                <div class="rating">
                    <span class="stars" data-rating="${produto.rating}"></span>
                    <span class="rating-text">${produto.rating}</span>
                </div>
                <a href="${produto.url}" class="card-title" target="_blank">${produto.name}</a>
                <p style="display: none!important;">${produto.price}</p>
            </div>
        </div>
    `;
    container.appendChild(card);
}

    
    // Atualiza as estrelas de avaliação
    document.querySelectorAll(".stars").forEach(el => {
        let rating = parseFloat(el.getAttribute("data-rating"));
        let starsHTML = "";
        
        for (let i = 1; i <= 5; i++) {
            if (rating >= 4.7) {
                starsHTML += '<i class="bi bi-star-fill"></i>';
            } else if (rating > 4.4 && rating < 4.6 && i === 5) {
                starsHTML += '<i class="bi bi-star-half"></i>';
            } else if (rating <= 4.4 && i === 5) {
                starsHTML += '<i class="bi bi-star"></i>';
            } else {
                starsHTML += '<i class="bi bi-star-fill"></i>';
            }
        }
        
        el.innerHTML = starsHTML;
    });
} catch (error) {
    console.error("Erro ao carregar os produtos:", error);
}
});



let products = [];

function addProduct() {
    let name = document.getElementById("name").value;
    let image = document.getElementById("image").value;
    let rating = parseFloat(document.getElementById("rating").value);
    let url = document.getElementById("url").value;
    let logo = document.getElementById("logo").value;

    let newProduct = { name, image, rating, url, logo };

    products.push(newProduct);

    document.getElementById("jsonOutput").value = JSON.stringify({ products }, null, 2);
}

function downloadJSON() {
    let dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify({ products }, null, 2));
    let downloadAnchor = document.createElement("a");
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", "catalogo.json");
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    document.body.removeChild(downloadAnchor);
}


function checkVisibility() {
    const banner = document.getElementById('bannerSlim');
    const rect = banner.getBoundingClientRect();
    const windowHeight = window.innerHeight || document.documentElement.clientHeight;

    if (rect.top <= windowHeight && rect.bottom >= 0) {
        banner.classList.add('banner-visible');
    }
}

function hideBanner() {
    const banner = document.getElementById('bannerSlim');
    banner.style.opacity = '0';
    setTimeout(() => {
        banner.style.display = 'none';
    }, 500); // Tempo do fade-out
}

document.addEventListener('scroll', checkVisibility);


document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll('.dropdown-submenu > a').forEach(function (element) {
        element.addEventListener("click", function (e) {
            if (window.innerWidth < 992) { 
                e.preventDefault(); 
                let submenu = this.nextElementSibling;
                if (submenu.style.display === "block") {
                    submenu.style.display = "none";
                } else {

                    document.querySelectorAll('.dropdown-menu .dropdown-menu').forEach(function (el) {
                        el.style.display = "none";
                    });
                    submenu.style.display = "block";
                }
            }
        });
    });
});
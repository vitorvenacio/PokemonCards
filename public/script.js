document.getElementById('fetch-button').addEventListener('click', async function () {
    const response = await fetch('http://localhost:9502/pokemon/fetch');
    const pokemonList = await response.json();

    const container = document.getElementById('pokemon-container');
    container.innerHTML = ''; // Limpa o conteúdo anterior

    pokemonList.forEach(pokemon => {
        const card = document.createElement('div');
        card.className = 'pokemon-card';
        const name = pokemon.name;


        card.innerHTML = `
            <h2>${name.toUpperCase()}</h2>
            <img src="${pokemon.image}" alt="${pokemon.name}">
            <h3>Moves:</h3>
            <ul>
                ${pokemon.moves.map(move => `
                    <li><b>${move.name}</b>: ${move.effect}</li>
                `).join('')}
            </ul>
        `;

        container.appendChild(card);
    });
});

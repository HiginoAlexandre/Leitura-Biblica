// Gerenciamento de abas de leitura
class BibleTabs {
    constructor() {
        this.tabs = [];
        this.activeTab = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.createTab(); // Cria primeira aba por padrão
    }

    createTab(book = null, chapter = null) {
        const tab = {
            id: Date.now(),
            book: book,
            chapter: chapter,
            element: null
        };

        this.tabs.push(tab);
        this.renderTab(tab);
        this.switchTab(tab.id);
        return tab;
    }

    renderTab(tab) {
        const tabsContainer = document.getElementById('bible-tabs');
        const tabElement = document.createElement('div');
        tabElement.className = 'bible-tab';
        tabElement.dataset.tabId = tab.id;
        tabElement.innerHTML = `
            <span>${tab.book ? `${tab.book} ${tab.chapter}` : 'Nova aba'}</span>
            <button class="close-tab" data-tab-id="${tab.id}">&times;</button>
        `;
        tabsContainer.appendChild(tabElement);
        tab.element = tabElement;
    }

    switchTab(tabId) {
        this.tabs.forEach(tab => {
            tab.element.classList.remove('active');
        });
        const tab = this.tabs.find(t => t.id === tabId);
        if (tab) {
            tab.element.classList.add('active');
            this.activeTab = tab;
            this.loadContent(tab);
        }
    }

    closeTab(tabId) {
        const index = this.tabs.findIndex(t => t.id === tabId);
        if (index > -1) {
            this.tabs[index].element.remove();
            this.tabs.splice(index, 1);
            if (this.activeTab.id === tabId) {
                const newTab = this.tabs[Math.max(0, index - 1)];
                if (newTab) {
                    this.switchTab(newTab.id);
                } else {
                    this.createTab();
                }
            }
        }
    }

    loadContent(tab) {
        if (tab.book && tab.chapter) {
            fetch(`/api/bible.php?book=${tab.book}&chapter=${tab.chapter}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('bible-content').innerHTML = this.formatChapter(data);
                });
        }
    }

    formatChapter(data) {
        let html = `<h2>${data.book} ${data.chapter}</h2>`;
        
        html += '<div class="verses">';
        data.verses.forEach(verse => {
            html += `
                <div class="verse">
                    <span class="verse-number">${verse.number}</span>
                    <div class="verse-content">
                        <div class="verse-english">${verse.text}</div>
                        ${verse.phonetic ? `<div class="phonetic">${verse.phonetic}</div>` : ''}
                        <div class="verse-portuguese">${verse.text_pt}</div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        return html;
    }

    setupEventListeners() {
        document.getElementById('new-tab-btn').addEventListener('click', () => this.createTab());
        document.getElementById('bible-tabs').addEventListener('click', (e) => {
            if (e.target.classList.contains('close-tab')) {
                this.closeTab(parseInt(e.target.dataset.tabId));
            } else if (e.target.classList.contains('bible-tab')) {
                this.switchTab(parseInt(e.target.dataset.tabId));
            }
        });

        document.getElementById('search-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const query = e.target.elements.query.value;
            fetch(`/api/bible.php?search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    const resultsContainer = document.getElementById('search-results');
                    resultsContainer.innerHTML = this.formatSearchResults(data.results);
                });
        });

        document.querySelectorAll('.verse').forEach(verse => {
            verse.addEventListener('click', () => {
                if (window.auth.isLoggedIn()) {
                    this.showAddToCollectionDialog(verse.dataset.book, verse.dataset.chapter, verse.dataset.verse);
                }
            });
        });
    }
}

// Gerenciamento de preferências do usuário
class UserPreferences {
    constructor() {
        this.preferences = {
            showPhonetics: true,
            darkMode: false,
            fontSize: 16
        };
        this.init();
    }

    init() {
        this.loadPreferences();
        this.setupEventListeners();
        this.applyPreferences();
    }

    loadPreferences() {
        const savedPrefs = localStorage.getItem('userPreferences');
        if (savedPrefs) {
            this.preferences = JSON.parse(savedPrefs);
        }
        
        // Sincronizar com as preferências do servidor se o usuário estiver logado
        if (window.auth.isLoggedIn()) {
            fetch('/api/preferences.php')
                .then(response => response.json())
                .then(data => {
                    if (data.preferences) {
                        this.preferences = { ...this.preferences, ...data.preferences };
                        this.applyPreferences();
                        this.savePreferences();
                    }
                });
        }
    }

    savePreferences() {
        localStorage.setItem('userPreferences', JSON.stringify(this.preferences));
        if (window.auth.isLoggedIn()) {
            fetch('/api/preferences.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.preferences)
            });
        }
    }

    applyPreferences() {
        // Aplicar modo noturno
        document.body.classList.toggle('dark-mode', this.preferences.darkMode);
        document.getElementById('darkModeSwitch').checked = this.preferences.darkMode;

        // Aplicar tamanho da fonte
        document.documentElement.style.setProperty('--font-size-base', `${this.preferences.fontSize}px`);
        document.getElementById('fontSizeRange').value = this.preferences.fontSize;

        // Aplicar visibilidade da transcrição fonética
        document.querySelectorAll('.phonetic').forEach(el => {
            el.style.display = this.preferences.showPhonetics ? 'block' : 'none';
        });
        document.getElementById('phoneticsSwitch').checked = this.preferences.showPhonetics;

        // Atualizar interface de preferências
        this.updatePreferencesUI();
    }

    updatePreferencesUI() {
        // Atualizar controles da barra de ferramentas
        document.getElementById('dark-mode-toggle').classList.toggle('active', this.preferences.darkMode);
        document.getElementById('font-size-control').value = this.preferences.fontSize;
        document.getElementById('show-phonetics-toggle').classList.toggle('active', this.preferences.showPhonetics);
    }

    setupEventListeners() {
        // Eventos da barra de ferramentas
        document.getElementById('dark-mode-toggle').addEventListener('click', () => {
            this.preferences.darkMode = !this.preferences.darkMode;
            this.applyPreferences();
            this.savePreferences();
        });

        document.getElementById('font-size-control').addEventListener('input', (e) => {
            this.preferences.fontSize = parseInt(e.target.value);
            this.applyPreferences();
            this.savePreferences();
        });

        document.getElementById('show-phonetics-toggle').addEventListener('click', () => {
            this.preferences.showPhonetics = !this.preferences.showPhonetics;
            this.applyPreferences();
            this.savePreferences();
        });

        // Eventos do modal de preferências
        document.getElementById('darkModeSwitch').addEventListener('change', (e) => {
            this.preferences.darkMode = e.target.checked;
            this.applyPreferences();
            this.savePreferences();
        });

        document.getElementById('fontSizeRange').addEventListener('input', (e) => {
            this.preferences.fontSize = parseInt(e.target.value);
            this.applyPreferences();
            this.savePreferences();
        });

        document.getElementById('phoneticsSwitch').addEventListener('change', (e) => {
            this.preferences.showPhonetics = e.target.checked;
            this.applyPreferences();
            this.savePreferences();
        });
    }
}

// Gerenciamento de coleções
class CollectionsManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        if (window.auth.isLoggedIn()) {
            this.loadCollections();
        }
    }

    loadCollections() {
        fetch('/api/bible.php?collections=true')
            .then(response => response.json())
            .then(data => {
                this.renderCollections(data);
            });
    }

    renderCollections(collections) {
        const container = document.getElementById('collections-list');
        container.innerHTML = collections.map(collection => `
            <div class="collection-card">
                <h3>${collection.name}</h3>
                <p>${collection.description}</p>
                <button class="btn btn-sm btn-primary" onclick="window.bibleApp.collections.openCollection(${collection.id})">
                    Abrir
                </button>
                <button class="btn btn-sm btn-danger" onclick="window.bibleApp.collections.deleteCollection(${collection.id})">
                    Excluir
                </button>
            </div>
        `).join('');
    }

    openCollection(id) {
        fetch(`/api/bible.php?collection_id=${id}`)
            .then(response => response.json())
            .then(data => {
                const tab = window.bibleApp.tabs.createTab();
                tab.element.querySelector('span').textContent = data.name;
                document.getElementById('bible-content').innerHTML = this.formatCollectionVerses(data.verses);
            });
    }

    deleteCollection(id) {
        if (confirm('Tem certeza que deseja excluir esta coleção?')) {
            fetch(`/api/bible.php?collections=${id}`, { method: 'DELETE' })
                .then(response => response.json())
                .then(() => this.loadCollections());
        }
    }

    formatCollectionVerses(verses) {
        return `
            <div class="verses">
                ${verses.map(verse => `
                    <div class="verse">
                        <span class="verse-number">${verse.verse}</span>
                        <div class="verse-content">
                            <div class="verse-reference">${verse.book} ${verse.chapter}:${verse.verse}</div>
                            <div class="verse-text">${verse.text}</div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    setupEventListeners() {
        document.getElementById('new-collection-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            fetch('/api/bible.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: formData.get('name'),
                    description: formData.get('description')
                })
            })
            .then(response => response.json())
            .then(() => {
                this.loadCollections();
                bootstrap.Modal.getInstance(document.getElementById('newCollectionModal')).hide();
            });
        });
    }
}

// Inicialização da aplicação
document.addEventListener('DOMContentLoaded', () => {
    window.bibleApp = {
        tabs: new BibleTabs(),
        preferences: new UserPreferences(),
        collections: new CollectionsManager(),
        preferences: new UserPreferences()
    };
});
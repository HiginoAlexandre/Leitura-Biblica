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
        // Implementar formatação do capítulo com versículos e fonética
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
        document.body.classList.toggle('dark-mode', this.preferences.darkMode);
        document.documentElement.style.setProperty('--font-size-base', `${this.preferences.fontSize}px`);
        document.querySelectorAll('.phonetic').forEach(el => {
            el.style.display = this.preferences.showPhonetics ? 'inline' : 'none';
        });
    }

    setupEventListeners() {
        document.getElementById('dark-mode-toggle').addEventListener('change', (e) => {
            this.preferences.darkMode = e.target.checked;
            this.applyPreferences();
            this.savePreferences();
        });

        document.getElementById('font-size-control').addEventListener('input', (e) => {
            this.preferences.fontSize = parseInt(e.target.value);
            this.applyPreferences();
            this.savePreferences();
        });

        document.getElementById('show-phonetics-toggle').addEventListener('change', (e) => {
            this.preferences.showPhonetics = e.target.checked;
            this.applyPreferences();
            this.savePreferences();
        });
    }
}

// Inicialização da aplicação
document.addEventListener('DOMContentLoaded', () => {
    window.bibleApp = {
        tabs: new BibleTabs(),
        preferences: new UserPreferences()
    };
});
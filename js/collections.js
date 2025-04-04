class Collections {
    constructor() {
        this.collections = [];
        this.setupEventListeners();
        this.loadCollections();
    }

    setupEventListeners() {
        document.getElementById('new-collection-form')?.addEventListener('submit', (e) => this.handleNewCollection(e));
        document.getElementById('collections-list')?.addEventListener('click', (e) => this.handleCollectionClick(e));
    }

    async loadCollections() {
        if (!window.auth?.isAuthenticated) return;

        try {
            const response = await fetch('/api/collections.php?action=list');
            const data = await response.json();
            if (data.success) {
                this.collections = data.collections;
                this.renderCollections();
            }
        } catch (error) {
            console.error('Erro ao carregar coleções:', error);
        }
    }

    async handleNewCollection(e) {
        e.preventDefault();
        if (!window.auth?.isAuthenticated) {
            alert('Faça login para criar uma coleção');
            return;
        }

        const formData = new FormData(e.target);
        try {
            const response = await fetch('/api/collections.php?action=create', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                this.collections.push(data.collection);
                this.renderCollections();
                bootstrap.Modal.getInstance(document.getElementById('newCollectionModal')).hide();
                e.target.reset();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Erro ao criar coleção:', error);
            alert('Erro ao criar coleção. Tente novamente.');
        }
    }

    async addToCollection(collectionId, verse) {
        if (!window.auth?.isAuthenticated) {
            alert('Faça login para adicionar à coleção');
            return;
        }

        try {
            const response = await fetch('/api/collections.php?action=add_verse', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    collection_id: collectionId,
                    verse: verse
                })
            });
            const data = await response.json();

            if (data.success) {
                alert('Versículo adicionado à coleção!');
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Erro ao adicionar versículo:', error);
            alert('Erro ao adicionar versículo. Tente novamente.');
        }
    }

    async removeFromCollection(collectionId, verse) {
        try {
            const response = await fetch('/api/collections.php?action=remove_verse', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    collection_id: collectionId,
                    verse: verse
                })
            });
            const data = await response.json();

            if (data.success) {
                this.loadCollections(); // Recarrega a coleção
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Erro ao remover versículo:', error);
            alert('Erro ao remover versículo. Tente novamente.');
        }
    }

    async deleteCollection(collectionId) {
        if (!confirm('Tem certeza que deseja excluir esta coleção?')) return;

        try {
            const response = await fetch(`/api/collections.php?action=delete&id=${collectionId}`, {
                method: 'DELETE'
            });
            const data = await response.json();

            if (data.success) {
                this.collections = this.collections.filter(c => c.id !== collectionId);
                this.renderCollections();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Erro ao excluir coleção:', error);
            alert('Erro ao excluir coleção. Tente novamente.');
        }
    }

    renderCollections() {
        const container = document.getElementById('collections-list');
        if (!container) return;

        if (this.collections.length === 0) {
            container.innerHTML = '<p class="text-muted">Nenhuma coleção encontrada.</p>';
            return;
        }

        container.innerHTML = this.collections.map(collection => `
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">${collection.name}</h5>
                    <p class="card-text">${collection.description || 'Sem descrição'}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-primary btn-sm view-collection" data-collection-id="${collection.id}">
                            Ver Versículos
                        </button>
                        <button class="btn btn-danger btn-sm delete-collection" data-collection-id="${collection.id}">
                            Excluir
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    handleCollectionClick(e) {
        const target = e.target;
        if (target.classList.contains('delete-collection')) {
            const collectionId = parseInt(target.dataset.collectionId);
            this.deleteCollection(collectionId);
        } else if (target.classList.contains('view-collection')) {
            const collectionId = parseInt(target.dataset.collectionId);
            this.viewCollection(collectionId);
        }
    }

    async viewCollection(collectionId) {
        try {
            const response = await fetch(`/api/collections.php?action=view&id=${collectionId}`);
            const data = await response.json();

            if (data.success) {
                const collection = data.collection;
                const versesHtml = collection.verses.map(verse => `
                    <div class="verse-item">
                        <p>${verse.book} ${verse.chapter}:${verse.verse}</p>
                        <p class="verse-text">${verse.text}</p>
                        <button class="btn btn-sm btn-danger remove-verse" 
                                data-collection-id="${collectionId}"
                                data-verse-id="${verse.id}">
                            Remover
                        </button>
                    </div>
                `).join('');

                const modal = new bootstrap.Modal(document.getElementById('viewCollectionModal'));
                document.getElementById('collection-verses').innerHTML = versesHtml;
                document.getElementById('collection-title').textContent = collection.name;
                modal.show();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Erro ao visualizar coleção:', error);
            alert('Erro ao visualizar coleção. Tente novamente.');
        }
    }
}

// Inicializa a classe Collections quando o documento estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    window.collections = new Collections();
});
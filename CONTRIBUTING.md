# Contributing to CheckEngine

First off, thank you for considering contributing to CheckEngine! 🎉

## 🤝 Code of Conduct

By participating in this project, you agree to be respectful and constructive.

## 🐛 Found a Bug?

1. **Check existing issues** - Someone might have already reported it
2. **Create a new issue** with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots if applicable
   - Your environment (OS, Docker version, etc.)

## 💡 Want to Add a Feature?

1. **Open an issue first** to discuss your idea
2. Wait for feedback before starting work
3. Reference the issue in your PR

## 🔧 Development Setup

See [README.md](README.md) for detailed setup instructions.

Quick start:
```bash
# Clone the repo
git clone https://github.com/yourusername/check-engine.git
cd check-engine

# Open in DevContainer (VS Code)
# Or manually:
docker-compose up -d
make install
```

## 📝 Pull Request Process

1. **Fork** the repository
2. **Create a branch** from `main`:
   ```bash
   git checkout -b feature/my-awesome-feature
   ```
3. **Make your changes**:
   - Write clear, self-documenting code
   - Add comments for complex logic
   - Follow existing code style
4. **Test your changes**:
   ```bash
   make test
   ```
5. **Commit** with clear messages:
   ```bash
   git commit -m "feat: add catalyst efficiency prediction"
   ```
6. **Push** to your fork:
   ```bash
   git push origin feature/my-awesome-feature
   ```
7. **Open a Pull Request** with:
   - Clear description of changes
   - Reference to related issues
   - Screenshots/GIFs for UI changes

## 📐 Coding Standards

### PHP (Symfony)
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/)
- Use type hints
- Document public methods

### Python (FastAPI)
- Follow [PEP 8](https://pep8.org/)
- Use type hints
- Run `black` and `isort` before committing:
  ```bash
  make format-python
  ```

### JavaScript/Vue.js
- Use ESLint + Prettier
- Run linter before committing:
  ```bash
  make lint-frontend
  ```

## 🧪 Testing

- Write tests for new features
- Ensure existing tests pass
- Aim for >80% code coverage

```bash
# Run all tests
make test

# Run specific tests
make test-symfony
make test-python
```

## 📚 Documentation

- Update README.md if you change functionality
- Add JSDoc/PHPDoc/docstrings for new functions
- Update API documentation if endpoints change

## 🎯 Priority Areas

We especially welcome contributions in:

- 🚗 **Vehicle support** - Add support for more vehicles
- 📊 **Visualizations** - Improve charts and graphs
- 🤖 **AI insights** - Enhance diagnostic intelligence
- 🌍 **i18n** - Translations
- 📱 **Mobile** - PWA improvements
- 🧪 **Tests** - Increase coverage

## ❓ Questions?

Open an issue with the `question` label or start a discussion.

## 📜 License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

**Thank you for contributing to CheckEngine!** 🚀

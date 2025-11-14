# Alphavel Database

Database package for Alphavel Framework.

## Installation

```bash
composer require alphavel/database
```

## Configuration

After installation, add these variables to your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=alphavel
DB_USERNAME=root
DB_PASSWORD=
```

For Docker environments, update `DB_HOST` to match your service name (e.g., `mysql`).

See `.env.example` in this package for a complete configuration template.

## Documentation

Visit [Alphavel Documentation](https://github.com/alphavel) for complete documentation.

## License

MIT License

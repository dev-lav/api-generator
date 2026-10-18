# API Generator

> Generate RESTful CRUD APIs for October CMS models and Tailor blueprints, straight from the backend.

Pick a model, choose a base endpoint and save. The plugin writes the controller and routes for you. You can limit the returned columns, eager load relations and protect an API with a bearer token.

## Features

- CRUD controller and routes generated on save, removed on delete
- Works with plugin models and Tailor blueprints (entries, streams, structures, singles and globals)
- Custom Condition to choose the returned columns and eager load relations
- Optional bearer token authentication with [RainLab.User](https://octobercms.com/plugin/rainlab-user)
- Lists every generated endpoint on the API edit page
- Consistent JSON response format

## Requirements

| Plugin version | October CMS | PHP |
| --- | --- | --- |
| 2.x | 4.x | 8.2+ |
| 1.x | 1.x – 3.x | see the `master` branch |

Dependencies:

- [RainLab.Builder](https://octobercms.com/plugin/rainlab-builder) (required)
- [RainLab.User](https://octobercms.com/plugin/rainlab-user) 3.x (optional, only needed for authentication)

## Installation

```bash
php artisan plugin:install AhmadFatoni.ApiGenerator
```

or with composer:

```bash
composer require ahmadfatoni/apigenerator-plugin
php artisan october:migrate
```

If composer runs out of memory, prefix the command with `COMPOSER_MEMORY_LIMIT=-1`.

## Usage

Open **API Generator** in the backend menu and click **Create**. You need the `Manage the API Generator` permission.

| Field | Description |
| --- | --- |
| API Name | Used for the controller class name. Letters, numbers and spaces, starting with a letter. |
| Base Endpoint | Base URL of the API, for example `api/v1/posts`. |
| Short Description | Optional note for yourself. |
| Select Model | A plugin model, or a Tailor blueprint shown as `Tailor: Blog\Post (stream)`. |
| Custom Condition | Optional JSON to limit columns and load relations, see below. |
| Require Authentication | Protect every endpoint of this API with a bearer token, see [Authentication](#authentication). |

Saving the form generates `controllers/api/{ApiName}Controller.php` and rebuilds `routes.php`. Both files are overwritten on every save, so do not edit them by hand.

### Custom Condition

Use `fillable` for the columns of the model, and `relation` for the relations to eager load and their columns. Both are comma separated lists.

```json
{
    "fillable": "id,title,slug,author_id",
    "relation": [
        {
            "name": "author",
            "fillable": "id,title"
        },
        {
            "name": "categories",
            "fillable": "id,title"
        }
    ]
}
```

For `belongsTo` relations, include the foreign key (for example `author_id`) in `fillable`, otherwise the relation comes back empty. Leave the field empty to return all columns without relations.

## Endpoints

For a Base Endpoint of `api/v1/posts`:

| Method | URL | Action |
| --- | --- | --- |
| GET | `api/v1/posts` | List all records |
| GET | `api/v1/posts/{id}` | Show a record |
| POST | `api/v1/posts` | Create a record |
| PUT / PATCH | `api/v1/posts/{id}` | Update a record |
| DELETE | `api/v1/posts/{id}` | Delete a record |
| GET | `api/v1/posts/{id}/delete` | Delete a record (legacy) |

Send the body as JSON (`Content-Type: application/json`) or `x-www-form-urlencoded`. PHP does not read `multipart/form-data` on PUT requests.

### Response format

Every response has the same shape:

```json
{
    "status_code": 200,
    "message": "success",
    "data": []
}
```

| Status | Message | When |
| --- | --- | --- |
| 200 | `success` | Read, update or delete succeeded |
| 201 | `created` | Record created, `data` contains the new `id` |
| 400 | `fail` / `bad request` | Validation failed or an unknown field was sent |
| 401 | `unauthorized` | Missing or invalid bearer token |
| 404 | `not found` | No record with that id |

### Example

```bash
curl -X POST "https://example.com/api/v1/posts" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{"title":"Hello","slug":"hello","content_group":"regular_post"}'
```

For Tailor entries that use groups, send `content_group` with the group handle from the blueprint.

## Authentication

Requires RainLab.User. Turn on **Require Authentication** for an API, and every one of its endpoints then needs a bearer token. APIs with the switch off stay public.

The plugin adds these endpoints while RainLab.User is installed:

| Method | URL | Description |
| --- | --- | --- |
| POST | `api/v1/auth/login` | Sign in with `email` and `password`, returns a token |
| POST | `api/v1/auth/refresh` | Returns a new token, needs a valid token |
| GET | `api/v1/auth/me` | Returns the current user, needs a valid token |

The login field follows the RainLab.User **Login attribute** setting, so use `username` instead of `email` if you changed it. Logins go through RainLab.User, so its throttling and events still apply. Accounts with two factor authentication enabled cannot sign in through the API.

Sign in:

```bash
curl -X POST "https://example.com/api/v1/auth/login" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{"email":"user@example.com","password":"secret"}'
```

```json
{
    "status_code": 200,
    "message": "success",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer",
        "expires_in": 3600,
        "user": {
            "id": 1,
            "first_name": "John",
            "last_name": "Smith",
            "email": "user@example.com"
        }
    }
}
```

Then send the token with every request to a protected API:

```bash
curl "https://example.com/api/v1/posts" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer TOKEN"
```

Tokens expire after 60 minutes. You can change the lifetime and signing key through the `rainlab.user::bearer_token` config.

## Upgrading from 1.x

- Version 2.x only supports October CMS 4. Keep using 1.x on older versions.
- Open each API in the backend and save it again to regenerate its controller.
- Tailor APIs created with 1.x need the model selected again, because Tailor options are now stored as `tailor:Handle`.
- The `fatoni/generate/api`, `fatoni/update/api` and `fatoni/delete/api` routes were removed. Generation now happens when you save in the backend.

## Contribute

Pull requests are welcome at [github.com/dev-lav/api-generator](https://github.com/dev-lav/api-generator).

## Contact

You can reach me on [LinkedIn](https://www.linkedin.com/in/ahmad-fatoni).

## License

This plugin is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

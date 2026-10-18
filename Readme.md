
# API Generator 

> October CMS plugin to build RESTful APIs.

## Features

  - Auto generate routes
  - Auto Generate Controller (CRUD)
  - Support relationship restful API

## Install
```
composer require AhmadFatoni.ApiGenerator
```

## Usage

### Form
- API Name : Name of your API module
- Base Endpoint : Base endpoint of your API, ex : api/v1/modulename
- Short Description : Describe your API
- Model : select model that will be created API
- Custom Condition : Build customer response using JSON modeling

### Custom Condition Example
```
{
    'fillable': 'id,title,content',
    'relation': [{
        'name': 'user',
        'fillable': 'id,first_name'
    }, {
        'name': 'categories',
        'fillable': 'id,name
    }]
}
```
* please replace single quote with quote

## Authentication

Requires the [RainLab.User](https://octobercms.com/plugin/rainlab-user) plugin. Enable **Require Authentication** on an API to protect all of its endpoints with a bearer token.

- `POST api/v1/auth/login` with `email` and `password` returns a `token`
- `POST api/v1/auth/refresh` returns a new token
- `GET api/v1/auth/me` returns the current user

Send the token in the header `Authorization: Bearer TOKEN`. Tokens expire after 60 minutes by default.

## Contribute

Pull Requests accepted.

## Contact

You can communicate with me using [linkedin](https://www.linkedin.com/in/ahmad-fatoni)

## License
The OctoberCMS platform is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

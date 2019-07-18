
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

## Contribute

Pull Requests accepted.

## Contact

You can communicate with me using [linkedin](https://www.linkedin.com/in/ahmad-fatoni)

## License
The OctoberCMS platform is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

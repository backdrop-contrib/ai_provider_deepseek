# AI Provider DeepSeek

DeepSeek provider for the Backdrop CMS AI module.

Adds DeepSeek (https://www.deepseek.com/) to the providers the `ai` module can
route to, using the OpenAI-compatible API at `https://api.deepseek.com`.

## Supported operations

| Operation | Supported | Notes |
|---|---|---|
| Chat | Yes | Streaming supported. JSON mode supported; JSON schema requests fall back to JSON mode (DeepSeek has no json_schema format). |
| Completions | Yes | Sent as a single chat message; uses the chat endpoint. |
| Tool calling | Yes | `deepseek-chat` only; the reasoner model does not support function calling. |
| Thinking | Yes | `deepseek-reasoner` (R1). |
| Vision | No | |
| Embeddings | No | |
| Image generation | No | |
| Moderation | No | |
| Speech-to-text | No | |

The model list is fetched from the API's `/models` endpoint, falling back to
`deepseek-chat` and `deepseek-reasoner` when the request fails. Temperature is
not sent for reasoner models, which reject it.

The base URL is stored in `ai_provider_deepseek.settings` (`base_url`) and can
be changed through configuration management if you use a proxy.

## Installation

- Install this module using the official [Backdrop CMS instructions](https://backdropcms.org/user-guide/modules).
- Create an authentication key with the Key module holding your DeepSeek API
  key (https://platform.deepseek.com/api_keys).
- Enable and configure the provider at `admin/config/ai/settings`.

## Issues

Bugs and feature requests should be reported in the [Issue Queue](https://github.com/backdrop-contrib/ai_provider_deepseek/issues).

## Current Maintainer

[Justin Keiser](https://github.com/keiserjb)

## Credits

- Created for Backdrop CMS by [Justin Keiser](https://github.com/keiserjb).

- Developed with AI assistance.

## License

This project is GPL v2 software. See the LICENSE.txt file in this directory for complete text.

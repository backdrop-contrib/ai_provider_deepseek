# AI Provider DeepSeek

DeepSeek provider for the Backdrop CMS AI module.

Adds DeepSeek (https://www.deepseek.com/) to the providers the `ai` module can
route to, using the OpenAI-compatible API at `https://api.deepseek.com`.

## Supported operations

| Operation | Supported | Notes |
|---|---|---|
| Chat | Yes | Streaming supported. JSON mode supported; JSON schema requests fall back to JSON mode (DeepSeek has no json_schema format). |
| Completions | Yes | Sent as a single chat message; uses the chat endpoint. |
| Tool calling | Yes | The model's `reasoning_content` is returned so agent tool loops can send it back, as DeepSeek requires in thinking mode. |
| Thinking | Yes | Thinking mode is on by default for current models. |
| Vision | No | |
| Embeddings | No | |
| Image generation | No | |
| Moderation | No | |
| Speech-to-text | No | |

The model list is fetched from the API's `/models` endpoint. There is no
built-in fallback list: DeepSeek renames models between generations. `/models`
has no capability metadata, so every model is offered for chat, tool calling
and thinking; adjust this on the Model capabilities page. Temperature is always
sent; DeepSeek ignores it in thinking mode.

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

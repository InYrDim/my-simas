# CONTRACT.md Template

Every module needs one of these at `Modules/<Name>/CONTRACT.md`. Copy this
template and fill in every section — no placeholders left in committed files.

```markdown
# Module: <Name>

## Owns

- Database tables: <list>
- Core domain concepts: <one-line description of what this module is responsible for>

## Public interface (Contracts/)

- `<Name>Contract` — <what it does, in one line>
- <repeat for each public interface/action other modules may call>

## Allowed dependencies

- Modules/Shared
- <any other module's App/Contracts/ this module is allowed to depend on, if any — lower layers only per the layer order in AGENTS.md>

## Events published

- `<EventName>` — fired when <condition>, payload: <fields>

## Events consumed

- `<EventName>` from `<OtherModule>` — handled by <ListenerName>, does <what>

## Explicitly NOT exposed

- <anything intentionally kept internal that might look tempting to reach into>

## Notes for maintainers

- <anything a future agent/dev needs to know that isn't obvious from the code>
- <for feature modules: the module key registered in ModuleRegistry and the
  permissions registered in PermissionRegistry>

Keep every section filled — the arch test
"every module has a filled CONTRACT.md" rejects missing or placeholder
files (including an empty Platform/Shared folder).
```

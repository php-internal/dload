## Guide: Translating Documents Using LLMs 🌍🤖

**Step 1: Prepare Your Prompt 📝**

Use a prompt like the following for your LLM agent to translate a document into your desired language. Replace `{TARGET_LANGUAGE}` with your specific target language:

```
Translate this markdown file from English to {TARGET_LANGUAGE}.
Ignore xml and html for translation.
I need a single message with all the md file content.
```

**Step 2: Upload the Document 📤**

- Attach or upload the actual document (e.g., a `.md` file) to the LLM agent for accurate results.

**Step 3: Run the Translation 🔄**

- Submit the prompt and the document to the LLM.
- Wait for the model to generate the translated output.

**Step 4: Review and Correct the Output 🧐✅**

- After translation, carefully review the output for any invalid or awkward translations.
- Manually correct any errors you find.
- Pay special attention to:
  - XML, HTML, and code blocks: Ensure these are not translated or altered.
  - Command examples and their arguments: Verify technical accuracy and formatting.
  - Technical terminology consistency throughout the document.
  - Cultural adaptations that make sense in target language context.
  - **Language naturalness**: Ensure the translation uses natural, living language that doesn't sound synthetic or machine-generated, while maintaining technical precision. Find the golden mean between conversational flow and technical accuracy.

**Step 5: Finalize and Save the Translated Document 💾**

- Once satisfied with the translation and corrections, save the document in the desired format.

---

**Step 6: Contribute Your Translation Using the Multilanguage README Pattern 🌐💾**

Once your translation is ready, you can contribute it to the project using the **Multilanguage README Pattern**. This approach helps keep documentation organized and accessible in multiple languages.

---

### 📚 What Is the Multilanguage README Pattern?

- This pattern is used in the project to manage README files in different languages.
- You can also apply it to any documentation file in your project, not just the README.

---

### 📝 How to Use the Pattern

1. **File Naming Convention**
    - Use ISO 639-1 in {lang_code} placeholder
    - Rename your translated file following this format:

```
your-doc-name-{lang_code}.md
```

        - Example:
            - `README-ru.md` for Russian
            - `INSTALL-es.md` for Spanish
2. **Add Your File to the Repository**
    - Place your translated file alongside the original document in the repository.
3. **Update Links (If Needed)**
    - If your project includes a language switcher or index, update it to reference your new translation.
4. **Reference the Guide**
    - For detailed steps, see the official guide:
      [how-to-use](https://github.com/jonatasemidio/multilanguage-readme-pattern/blob/master/STEPS.md)

---

### 🛠️ Example

If you translated `README.md` into French, name your file:

```
README-fr.md
```

If you translated `CONTRIBUTING.md` into German, name it:

```
CONTRIBUTING-de.md
```

---

### Tips for High-Quality LLM Translation 🚀

- Use clear and specific prompts to minimize errors.
- Always double-check technical content, as LLMs may mistranslate code or markup.
- **Prioritize natural language flow**: Avoid overly literal translations that sound robotic. The text should read as if written by a native speaker while preserving technical accuracy.
- Consider using frameworks like MAPS (Multi-Aspect Prompting and Selection) for complex translations, which guide the LLM through keywords, topics, and relevant examples to improve accuracy and reduce errors.
- Remember: Human review is essential for catching subtle mistakes and ensuring the translation meets your quality standards.

---

**Example Prompt:**

```
Translate this markdown file from English to Chinese
xml and html ignore for translate
i need a single message with all md file content
```

---

With these steps, you can efficiently translate documents using LLMs while maintaining accuracy and consistency! 🌐✨

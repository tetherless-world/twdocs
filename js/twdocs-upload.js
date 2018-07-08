const twDialog = (alt, username) => { return `<div> \
            <input class="tw-file-attach" type="button" value="Select File" onclick="twForceClick(\'fileToUpload\')" /> \
        </div> \
        <div id="file-line" class="tw-file-line-name"></div> \
        <input class="tw-file-selection" type="file" name="fileToUpload" id="fileToUpload" onchange="twFileChanged(\'file-line\')" /> \
        <div> \
            <button class="tw-file-submit" onclick="((event) => twDoUpload('${alt}', '${username}', event))(event)">Upload File</button> \
        </div>`
}

let twUploadFile

function twOpenDialog(id, alt, username, event) {
    twCloseDialog()
    const element = document.querySelector(`#${id}`)
    const dom = document.createElement('div')
    dom.classList = 'tw-dialog'
    dom.setAttribute('style', `top:${event.clientY}px;left:${event.clientX}px`)
    dom.innerHTML = twDialog(alt, username)
    element.appendChild(dom)
}

function twCloseDialog() {
    const element = document.querySelector('.tw-dialog')
    let parent
    if (element) {
        parent = element.parentElement
        parent.removeChild(element)
    }
    twUploadFile = null
    return parent
}

function twForceClick(id) {
    const element = document.getElementById(id)
    element.click()
}

function twFileChanged(id) {
    if (window.event.currentTarget.files && window.event.currentTarget.files[0]) {
        twUploadFile = window.event.currentTarget.files.item(0)
        const element = document.getElementById(id)
        element.innerText = `${twUploadFile.name.substr(0, 40)}...`
        console.log(twUploadFile)
    }
}

function twArrayBufferToString(buffer) {
    var binary = '';
    var bytes = new Uint8Array(buffer);
    var len = bytes.byteLength;
    for (var i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return binary
}

function twArrayBufferToBase64(buffer) {
    return window.btoa(twArrayBufferToString(buffer));
}

function twDoUpload(alt, username, e) {
    if (twUploadFile) {
        e.preventDefault()
        const reader = new FileReader()
        reader.readAsArrayBuffer(twUploadFile)
        reader.onload = (src) => {
            const data = new FormData()
            data.append('name', twUploadFile.name)
            data.append('alt', alt)
            data.append('username', username)
            data.append('source', twArrayBufferToBase64(src.target.result))
            let errObj = ''
            fetch('/media/submit.php',
                { method: 'post', body: data }
            ).then(async (response) => {
                const element = twCloseDialog()
                const reader = response.body.getReader()
                const getContents = () => {
                  return reader.read().then(({done, value}) => {
                    if(done) {
                      return
                    } else {
                      errObj = errObj + twArrayBufferToString(value)
                      return getContents()
                    }
                  })
                }
                await getContents()
                console.log(errObj)
                const responseObj = JSON.parse(errObj)
                if (response.status !== 200) {
                  alert(`${responseObj.error}`)
                } else {
                  location.reload(true)
                }
            }).catch((((err) => {
              console.log(err)
              alert('Unable to upload the file, internal error')
            })))
        }
    }
}

document.onkeydown = function (evt) {
    evt = evt || window.event;
    if (evt.keyCode == 27) {
        twCloseDialog()
    }
};

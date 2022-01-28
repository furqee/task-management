<template>
  <div class="row">
    <div class="col-lg-12 m-auto">
      <!-- Container -->
      <Container
        orientation="horizontal"
        drag-handle-selector=".column-drag-handle"
        :drop-placeholder="upperDropPlaceholderOptions"
        @drop="onColumnDrop($event)"
        @drag-start="dragStart"
      >
        <!-- Draggable -->
        <Draggable v-for="column in scene.columns" :key="column.id">
          <div class="card-container">
            <div class="card-column-header">
              <span class="column-drag-handle">&#x2630;</span>
              {{ column.name }}
            </div>
            <Container
              group-name="col"
              :get-child-payload="getCardPayload(column.id)"
              drag-class="card-ghost"
              drop-class="card-ghost-drop"
              :drop-placeholder="dropPlaceholderOptions"
              @drop="(e) => onCardDrop(column.id, e)"
            >
              <Draggable v-for="(card, index) in column.tasks" :key="card.id">
                <div class="card" :style="card.props.style" @click="onCardClick(card, index)">
                  <h4>{{ card.title }}</h4>
                  <p>{{ card.description }}</p>
                </div>
              </Draggable>
            </Container>
          </div>
        </Draggable>
      </Container>
    </div>

    <!-- Modal - Task Edit -->
    <b-modal
      id="modal-prevent-closing"
      ref="modal"
      title="Task Details"
      @ok="handleOk"
    >
      <form>
        <!-- Title -->
        <div class="mb-3 row">
          <label class="col-md-2 col-form-label text-md-end">Title</label>
          <div class="col-md-10">
            <input v-model="form.title" :class="{ 'is-invalid': form.errors.has('title') }" class="form-control" type="text" name="title">
            <has-error :form="form" field="title" />
          </div>
        </div>

        <!-- Description -->
        <div class="mb-3 row">
          <label class="col-md-2 col-form-label text-md-end">Description</label>
          <div class="col-md-10">
            <textarea v-model="form.description" :class="{ 'is-invalid': form.errors.has('description') }" class="form-control" cols="30" rows="10" name="description" />
            <has-error :form="form" field="description" />
          </div>
        </div>

        <!-- Status -->
        <div class="mb-3 row">
          <label class="col-md-2 col-form-label text-md-end">Status</label>
          <div class="col-md-10">
            <b-form-select v-model="form.status_id" :options="options" value-field="id" text-field="status" :class="{ 'is-invalid': form.errors.has('status_id') }" class="form-control" name="status_id" />
            <has-error :form="form" field="status_id" />
          </div>
        </div>
      </form>
    </b-modal>
  </div>
</template>

<script>
import { Container, Draggable } from 'vue-smooth-dnd'
import { applyDrag } from '../utils'
import axios from 'axios'
import Form from 'vform'
import Swal from 'sweetalert2/dist/sweetalert2.js'

export default {
  name: 'Cards',
  components: { Container, Draggable },
  data () {
    return {
      scene: {},
      upperDropPlaceholderOptions: {
        className: 'cards-drop-preview',
        animationDuration: '150',
        showOnTop: true
      },
      dropPlaceholderOptions: {
        className: 'drop-preview',
        animationDuration: '150',
        showOnTop: true
      },
      options: [],
      form: new Form({
        id: null,
        title: null,
        description: null,
        status_id: null
      })
    }
  },
  mounted () {
    axios
      .get('/api/task/get-all')
      .then(response => {
        this.scene = response.data.tasks
        this.options = response.data.statuses
      })
      .catch(error => {
        console.log(error)
        this.errored = true
      })
  },
  methods: {
    onColumnDrop (dropResult) {
      const scene = Object.assign({}, this.scene)
      scene.columns = applyDrag(scene.columns, dropResult)
      this.scene = scene
    },
    onCardDrop (columnId, dropResult, update = true) {
      // If card added or remove sort array
      if (dropResult.removedIndex !== null || dropResult.addedIndex !== null) {
        const scene = Object.assign({}, this.scene)
        const column = scene.columns.filter(p => p.id === columnId)[0]
        const columnIndex = scene.columns.indexOf(column)
        const newColumn = Object.assign({}, column)
        newColumn.tasks = applyDrag(newColumn.tasks, dropResult)
        scene.columns.splice(columnIndex, 1, newColumn)
        this.scene = scene
      }
      // If card added only
      if (dropResult.removedIndex === null && dropResult.addedIndex !== null && update) {
        this.updateTaskStatus(columnId, dropResult)
      }
    },
    getCardPayload (columnId) {
      return index => {
        return this.scene.columns.filter(p => p.id === columnId)[0].tasks[index]
      }
    },
    onCardClick (card, index) {
      this.card = card
      this.card.index = index
      this.form.keys().forEach(key => {
        this.form[key] = card[key]
      })
      this.$refs.modal.show()
    },
    handleOk (bvModalEvt) {
      // Prevent modal from closing
      bvModalEvt.preventDefault()
      // Trigger submit handler
      this.updateTask()
    },
    async updateTask () {
      await this.form.patch('/api/task/update')
        .then(response => {
          // Removing from old column
          this.onCardDrop(this.card.status_id, {
            addedIndex: null,
            payload: this.card,
            removedIndex: this.card.index
          }, false)
          // Adding into new column
          this.card.title = this.form.title
          this.card.status_id = this.form.status_id
          this.card.description = this.form.description
          this.onCardDrop(this.card.status_id, {
            addedIndex: 0,
            payload: this.card,
            removedIndex: null
          }, false)
          // Hide the modal manually
          this.$nextTick(() => {
            this.$bvModal.hide('modal-prevent-closing')
          })
          Swal.fire({
            position: 'top-end',
            icon: 'success',
            title: response.data.message,
            showConfirmButton: false,
            timer: 3000
          })
        })
        .catch(error => {
          console.log(error)
          this.errored = true
        })
    },
    updateTaskStatus (columnId, dropResult) {
      axios
        .patch('/api/task/update-status', {
          id: dropResult.payload.id,
          status_id: columnId
        })
        .then(response => {
          const scene = Object.assign({}, this.scene)
          // eslint-disable-next-line array-callback-return
          scene.columns.filter(p => {
            if (p.id === columnId) {
              p.tasks.filter(q => {
                if (q.id === dropResult.payload.id) {
                  q.status_id = columnId
                }
                return q
              })
              return p
            }
          })
          Swal.fire({
            position: 'top-end',
            icon: 'success',
            title: response.data.message,
            showConfirmButton: false,
            timer: 3000
          })
        })
        .catch(error => {
          console.log(error)
          this.errored = true
        })
    },
    dragStart () {
      // console.log('drag started')
    },
    log (...params) {
      // console.log(...params)
    }
  }
}
</script>

<style scoped>
.smooth-dnd-container.horizontal > .smooth-dnd-draggable-wrapper {
  padding-right: 1em;
}
.smooth-dnd-container.vertical > .smooth-dnd-draggable-wrapper {
  padding-bottom: 0.5em;
  cursor: pointer;
}
p {
  padding-left: 0.5em;
  padding-right: 0.5em;
}
.card-column-header {
  font-size: 1.3em;
}
.card-container {
  background: white;
  padding: 0.5em;
  box-shadow: 0 2px 4px 0 rgb(0 0 0 / 10%);
}
.smooth-dnd-container.vertical {
  margin-top: 1em;
}
h4 {
  padding-left: 0.3em;
  padding-top: 0.5em;
}
.custom-select {
  width: 100%;
}
.text-md-end {
  text-align: left !important;
}
</style>
